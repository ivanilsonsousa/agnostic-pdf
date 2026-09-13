<?php

declare(strict_types=1);

namespace AgnosticPDF\Drivers;

use AgnosticPDF\Contracts\PdfSignerInterface;
use AgnosticPDF\Exceptions\PdfSignatureException;
use AgnosticPDF\Signatures\Certificate;
use AgnosticPDF\Signatures\CertificateInfo;
use AgnosticPDF\Signatures\Internal\CmsSigner;
use AgnosticPDF\Signatures\Internal\CmsVerifier;
use AgnosticPDF\Signatures\Internal\IncrementalSignatureWriter;
use AgnosticPDF\Signatures\Internal\PdfSignatureLocator;
use AgnosticPDF\Signatures\SignatureAppearance;
use AgnosticPDF\Signatures\SignatureOptions;
use AgnosticPDF\Signatures\SignatureVerification;
use AgnosticPDF\Signatures\TrustStore;
use AgnosticPDF\Signatures\VerificationResult;
use DateTimeImmutable;
use Papier\Objects\PdfString;
use Papier\Parser\PdfParser;
use Throwable;

final readonly class PapierPdfSigner implements PdfSignerInterface
{
  public function __construct(
    private PdfSignatureLocator $locator = new PdfSignatureLocator(),
    private IncrementalSignatureWriter $writer = new IncrementalSignatureWriter(),
    private CmsSigner $cmsSigner = new CmsSigner(),
    private CmsVerifier $cmsVerifier = new CmsVerifier(),
  ) {
  }

  public function sign(
    string $input,
    Certificate $certificate,
    ?SignatureAppearance $appearance = null,
    ?SignatureOptions $options = null,
  ): string {
    $this->assertPdf($input);
    $options ??= SignatureOptions::make();

    try {
      $parser = new PdfParser($input);
      $parser->parse();
      $permission = $this->locator->docMdpPermission($parser);

      if ($permission === 1) {
        throw new PdfSignatureException(
          'The existing DocMDP certification forbids every subsequent document change.',
        );
      }

      $existingSignatures = $this->locator->locate($parser);
      $fieldName          = $options->fieldNameValue() ?? 'Signature' . (count($existingSignatures) + 1);
      $dictionary         = $this->signatureDictionary($options);

      return $this->writer->append(
        $parser,
        $dictionary,
        $options->signatureCapacityBytes(),
        fn (string $content): string => $this->cmsSigner->sign($content, $certificate),
        $appearance,
        $fieldName,
        $options->signerName(),
        $options->signingTime(),
      );
    } catch (PdfSignatureException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      throw new PdfSignatureException('Unable to parse or incrementally sign the PDF.', previous: $exception);
    }
  }

  public function verify(string $input, ?TrustStore $trustStore = null): VerificationResult
  {
    $this->assertPdf($input);

    try {
      $parser = new PdfParser($input);
      $parser->parse();
      $records = $this->locator->locate($parser);
    } catch (Throwable $exception) {
      throw new PdfSignatureException('Unable to parse the PDF for signature verification.', previous: $exception);
    }

    $signatures = [];

    foreach ($records as $offset => $record) {
      [$start, $firstLength, $secondStart, $secondLength] = $record->byteRange;
      $revisionLength                                     = $secondStart + $secondLength;
      $rangeIsValid                                       = $start === 0
        && $firstLength >= 0
        && $secondStart > $firstLength
        && $secondLength >= 0
        && $revisionLength <= strlen($input)
        && substr($input, $firstLength, 1)     === '<'
        && substr($input, $secondStart - 1, 1) === '>';
      $verification = null;

      if ($rangeIsValid) {
        $signedContent = substr($input, $start, $firstLength)
          . substr($input, $secondStart, $secondLength);
        $verification = $this->cmsVerifier->verify($record->cms, $signedContent, $trustStore);
      }

      $signedAt               = $this->pdfDate($record->signedAt);
      $certificate            = $this->certificateInfo($verification?->certificatePem);
      $cryptographicallyValid = $rangeIsValid && ($verification?->valid ?? false);

      $signatures[] = new SignatureVerification(
        index: $offset + 1,
        fieldName: $record->fieldName,
        signerName: $record->signerName,
        reason: $record->reason,
        location: $record->location,
        cryptographicallyValid: $cryptographicallyValid,
        documentIntegrityValid: $cryptographicallyValid,
        certificateTrusted: $verification?->trusted,
        certificate: $certificate,
        signedAt: $signedAt,
        certificateValidAtSigningTime: $certificate !== null
          && $signedAt                              !== null
          && $certificate->isValidAt($signedAt),
        algorithm: $this->algorithm($record->cms, $certificate),
        coversRevision: $rangeIsValid,
        revisionLength: $rangeIsValid ? $revisionLength : 0,
        hasLaterChanges: $rangeIsValid && $revisionLength < strlen($input),
        error: $rangeIsValid ? $verification?->error : 'Invalid PDF ByteRange.',
      );
    }

    return new VerificationResult($signatures);
  }

  private function signatureDictionary(SignatureOptions $options): string
  {
    $capacity   = $options->signatureCapacityBytes();
    $date       = $options->signingTime()->setTimezone(new \DateTimeZone('UTC'))->format('YmdHis');
    $dictionary = "<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached\n";
    $dictionary .= "/ByteRange [0000000000 0000000000 0000000000 0000000000]\n";
    $dictionary .= '/Contents <' . str_repeat('0', $capacity * 2) . ">\n";
    $dictionary .= '/M ' . PdfString::literal('D:' . $date . 'Z')->toString() . "\n";

    foreach ([
      'Name'        => $options->signerName(),
      'Reason'      => $options->reasonValue(),
      'Location'    => $options->locationValue(),
      'ContactInfo' => $options->contactInfoValue(),
    ] as $key => $value) {
      if ($value !== null) {
        $dictionary .= '/' . $key . ' ' . PdfString::text($value)->toString() . "\n";
      }
    }

    return $dictionary . '>>';
  }

  private function assertPdf(string $input): void
  {
    if (!str_starts_with(ltrim($input), '%PDF-')) {
      throw new PdfSignatureException('Input is not a PDF document.');
    }
  }

  private function pdfDate(?string $value): ?DateTimeImmutable
  {
    if ($value === null || preg_match('/^D:(\d{14})(Z|[+-]\d{2}\'?\d{2}\'?)?/', $value, $match) !== 1) {
      return null;
    }

    $timezone = $match[2] ?? 'Z';
    $timezone = $timezone === '' || $timezone === 'Z'
      ? '+00:00'
      : substr($timezone, 0, 3) . ':' . substr(str_replace("'", '', $timezone), 3, 2);
    $date = DateTimeImmutable::createFromFormat('!YmdHisP', $match[1] . $timezone);

    return $date === false ? null : $date;
  }

  private function certificateInfo(?string $pem): ?CertificateInfo
  {
    if ($pem === null) {
      return null;
    }

    try {
      return CertificateInfo::fromPem($pem);
    } catch (Throwable) {
      return null;
    }
  }

  private function algorithm(string $cms, ?CertificateInfo $certificate): string
  {
    $digest = match (true) {
      str_contains($cms, hex2bin('0609608648016503040203')) => 'sha512',
      str_contains($cms, hex2bin('0609608648016503040202')) => 'sha384',
      str_contains($cms, hex2bin('0609608648016503040201')) => 'sha256',
      str_contains($cms, hex2bin('06052b0e03021a'))         => 'sha1',
      default                                               => 'unknown',
    };

    if ($certificate === null) {
      return $digest;
    }

    $key     = openssl_pkey_get_public($certificate->pem);
    $details = $key === false ? false : openssl_pkey_get_details($key);
    $keyType = is_array($details) ? ($details['type'] ?? null) : null;
    $suffix  = match ($keyType) {
      OPENSSL_KEYTYPE_RSA => 'RSA',
      OPENSSL_KEYTYPE_EC  => 'ECDSA',
      default             => null,
    };

    return $suffix === null ? $digest : $digest . 'With' . $suffix;
  }
}
