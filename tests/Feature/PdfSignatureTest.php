<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Drivers\PapierPdfSigner;
use AgnosticPDF\Exceptions\PdfSignatureException;
use AgnosticPDF\Signatures\Certificate;
use AgnosticPDF\Signatures\SignatureAppearance;
use AgnosticPDF\Signatures\SignatureOptions;
use AgnosticPDF\Signatures\TrustStore;
use DateTimeImmutable;
use Papier\Objects\PdfArray;
use Papier\Objects\PdfDictionary;
use Papier\Objects\PdfIndirectReference;
use Papier\Objects\PdfInteger;
use Papier\Objects\PdfName;
use Papier\Parser\PdfParser;
use Papier\Signature\PdfSigner as ExternalPdfSigner;
use Papier\Writer\IncrementalUpdater;
use Tests\TestCase;

final class PdfSignatureTest extends TestCase
{
  private PapierPdfSigner $signer;
  private Certificate $certificate;

  protected function setUp(): void
  {
    parent::setUp();

    $this->signer      = new PapierPdfSigner();
    $this->certificate = $this->certificate('Signer A');
  }

  public function test_it_adds_an_invisible_cryptographic_signature_as_an_incremental_revision(): void
  {
    $original     = $this->pdf();
    $signed       = $this->signer->sign($original, $this->certificate);
    $verification = $this->signer->verify($signed);

    $this->assertStringStartsWith($original, $signed);
    $this->assertSame(2, substr_count($signed, '%%EOF'));
    $this->assertStringContainsString('/ByteRange [', $signed);
    $this->assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $signed);
    $this->assertSame(1, $verification->count());
    $this->assertTrue($verification->allCryptographicallyValid());
    $this->assertNull($verification->allTrusted());
    $this->assertSame('Signer A', $verification->signatures[0]->certificate?->subject['commonName']);
  }

  public function test_it_uses_a_complete_image_as_the_visible_signature_appearance(): void
  {
    $appearance = SignatureAppearance::make()
      ->page(1)
      ->position(36, 24)
      ->size(280, 71)
      ->image((string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
      ));
    $signed = $this->signer->sign($this->pdf(), $this->certificate, $appearance);

    $this->assertStringContainsString('/Subtype /Image', $signed);
    $this->assertStringContainsString('/Subtype /Widget', $signed);
    $this->assertMatchesRegularExpression('/\/Rect \[36(?:\.0)? 24(?:\.0)? 316(?:\.0)? 95(?:\.0)?\]/', $signed);
    $this->assertTrue($this->signer->verify($signed)->allCryptographicallyValid());
  }

  public function test_it_uses_a_pdf_page_as_a_vector_signature_appearance_with_author_metadata(): void
  {
    $appearance = SignatureAppearance::make()
      ->page(1)
      ->position(36, 24)
      ->size(280, 71)
      ->pdf((string) file_get_contents(__DIR__ . '/../Examples/sample-2.pdf'));
    $signedAt = new DateTimeImmutable('2026-09-12T20:37:19Z');
    $signed   = $this->signer->sign(
      $this->pdf(),
      $this->certificate,
      $appearance,
      SignatureOptions::make()->signer('Signer A')->signedAt($signedAt),
    );
    $verification = $this->signer->verify($signed);
    $parser       = new PdfParser($signed);
    $parser->parse();

    $this->assertTrue($verification->allCryptographicallyValid());
    $this->assertSame('Signature1.Signer A', $verification->signatures[0]->fieldName);
    $this->assertSame('Signer A', $verification->signatures[0]->signerName);
    $this->assertStringContainsString('/Subtype /Form', $signed);
    $this->assertStringContainsString('/M (D:20260912203719Z)', $signed);
    $this->assertSame(
      'Documento assinado digitalmente por Signer A',
      $parser->getAnnotations()[0]['contents'],
    );
  }

  public function test_three_incremental_signatures_all_remain_valid(): void
  {
    $original     = $this->pdf();
    $certificates = [
      $this->certificate('Signer A'),
      $this->certificate('Signer B'),
      $this->certificate('Signer C'),
    ];
    $signed = $original;

    foreach ($certificates as $index => $certificate) {
      $signed = $this->signer->sign(
        $signed,
        $certificate,
        null,
        SignatureOptions::make()->signer('Signer ' . chr(65 + $index)),
      );
    }

    $verification = $this->signer->verify($signed);

    $this->assertStringStartsWith($original, $signed);
    $this->assertSame(4, substr_count($signed, '%%EOF'));
    $this->assertSame(3, $verification->count());
    $this->assertTrue($verification->allCryptographicallyValid());
    $this->assertTrue($verification->signatures[0]->hasLaterChanges);
    $this->assertTrue($verification->signatures[1]->hasLaterChanges);
    $this->assertFalse($verification->signatures[2]->hasLaterChanges);
    $this->assertSame(
      ['Signer A', 'Signer B', 'Signer C'],
      array_map(fn ($signature) => $signature->certificate?->subject['commonName'], $verification->signatures),
    );
  }

  public function test_it_preserves_a_signature_created_by_another_signer(): void
  {
    $externalCertificate = $this->certificate('External Signer');
    $externallySigned    = (new ExternalPdfSigner(
      $externalCertificate->certificatePem(),
      $externalCertificate->privateKeyPem(),
    ))->setName('External Signer')->sign($this->pdf());

    $signedAgain = $this->signer->sign(
      $externallySigned,
      $this->certificate,
      options: SignatureOptions::make()->signer('Signer A'),
    );
    $verification = $this->signer->verify($signedAgain);

    $this->assertStringStartsWith($externallySigned, $signedAgain);
    $this->assertSame(2, $verification->count());
    $this->assertTrue($verification->allCryptographicallyValid());
    $this->assertTrue($verification->signatures[0]->hasLaterChanges);
    $this->assertFalse($verification->signatures[1]->hasLaterChanges);
  }

  public function test_a_self_signed_certificate_is_valid_but_untrusted_until_explicitly_trusted(): void
  {
    $signed = $this->signer->sign($this->pdf(), $this->certificate);

    $withoutTrust = $this->signer->verify($signed);
    $withTrust    = $this->signer->verify(
      $signed,
      TrustStore::fromPem($this->certificate->certificatePem()),
    );

    $this->assertTrue($withoutTrust->signatures[0]->cryptographicallyValid);
    $this->assertNull($withoutTrust->signatures[0]->certificateTrusted);
    $this->assertTrue($withTrust->signatures[0]->certificateTrusted);
  }

  public function test_it_reports_a_certificate_outside_its_claimed_signing_date(): void
  {
    foreach (['2000-01-01T00:00:00Z', '2100-01-01T00:00:00Z'] as $date) {
      $signed = $this->signer->sign(
        $this->pdf(),
        $this->certificate,
        options: SignatureOptions::make()->signedAt(new DateTimeImmutable($date)),
      );
      $signature = $this->signer->verify($signed)->signatures[0];

      $this->assertTrue($signature->cryptographicallyValid);
      $this->assertFalse($signature->certificateValidAtSigningTime);
    }
  }

  public function test_it_detects_changes_to_bytes_covered_by_the_signature(): void
  {
    $signed       = $this->signer->sign($this->pdf(), $this->certificate);
    $tampered     = substr_replace($signed, '%PDF-1.6', 0, 8);
    $verification = $this->signer->verify($tampered);

    $this->assertFalse($verification->signatures[0]->cryptographicallyValid);
    $this->assertFalse($verification->signatures[0]->documentIntegrityValid);
  }

  public function test_an_unsigned_pdf_returns_an_empty_result(): void
  {
    $verification = $this->signer->verify($this->pdf());

    $this->assertFalse($verification->isSigned());
    $this->assertSame(0, $verification->count());
  }

  public function test_a_corrupt_pdf_is_rejected(): void
  {
    $this->expectException(PdfSignatureException::class);

    $this->signer->verify('%PDF-1.7 definitely not a PDF');
  }

  public function test_doc_mdp_permission_one_is_rejected_before_signing(): void
  {
    $pdf = $this->pdfWithDocMdpPermission(1);

    $this->expectException(PdfSignatureException::class);
    $this->expectExceptionMessage('DocMDP');

    $this->signer->sign($pdf, $this->certificate);
  }

  private function certificate(string $commonName): Certificate
  {
    return Certificate::create()
      ->commonName($commonName)
      ->organization('Agnostic PDF Tests')
      ->country('BR')
      ->validFor(30)
      ->generate();
  }

  private function pdf(): string
  {
    return (string) file_get_contents(__DIR__ . '/../Examples/sample-1.pdf');
  }

  private function pdfWithDocMdpPermission(int $permission): string
  {
    $parser = new PdfParser($this->pdf());
    $parser->parse();
    $catalogNumber = $parser->getXref()->getCatalogObjectNumber();
    $catalog       = $catalogNumber === null ? null : $parser->resolveObject($catalogNumber);
    $this->assertInstanceOf(PdfDictionary::class, $catalog);
    $updater = new IncrementalUpdater($parser);

    $parameters = new PdfDictionary();
    $parameters->set('Type', new PdfName('TransformParams'));
    $parameters->set('P', new PdfInteger($permission));
    $reference = new PdfDictionary();
    $reference->set('TransformMethod', new PdfName('DocMDP'));
    $reference->set('TransformParams', $parameters);
    $references = new PdfArray($reference);
    $signature  = new PdfDictionary();
    $signature->set('Type', new PdfName('Sig'));
    $signature->set('Reference', $references);
    $signatureNumber = $updater->addObject($signature);
    $permissions     = new PdfDictionary();
    $permissions->set('DocMDP', new PdfIndirectReference($signatureNumber));
    $catalog->set('Perms', $permissions);
    $updater->updateObject($catalogNumber, $catalog);

    return $updater->build();
  }
}
