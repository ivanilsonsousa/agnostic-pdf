<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures;

use AgnosticPDF\Contracts\CertificateIssuerInterface;
use AgnosticPDF\Exceptions\CertificateException;
use AgnosticPDF\Signatures\Internal\SecureTemporaryFiles;
use Throwable;

final class SelfSignedCertificateIssuer implements CertificateIssuerInterface
{
  public function issue(CertificateRequest $request): Certificate
  {
    $files = new SecureTemporaryFiles();

    try {
      $configPath = $files->write(<<<'OPENSSL'
        [ req ]
        distinguished_name = subject
        prompt = no

        [ subject ]

        [ signing_certificate ]
        basicConstraints = critical, CA:FALSE
        keyUsage = critical, digitalSignature, nonRepudiation
        subjectKeyIdentifier = hash
        OPENSSL, 'agnostic_pdf_openssl_');
      $configuration = [
        'config'           => $configPath,
        'private_key_bits' => $request->keyBits,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'digest_alg'       => $request->digestAlgorithm,
      ];
      $key = openssl_pkey_new($configuration);

      if ($key === false) {
        throw new CertificateException('Unable to generate a private key: ' . self::lastOpenSslError());
      }

      $csr = openssl_csr_new($request->distinguishedName, $key, $configuration);

      if ($csr === false) {
        throw new CertificateException('Unable to generate a certificate request: ' . self::lastOpenSslError());
      }

      $x509 = openssl_csr_sign(
        $csr,
        null,
        $key,
        $request->validDays,
        [...$configuration, 'x509_extensions' => 'signing_certificate'],
        $request->serialNumber ?? random_int(1, PHP_INT_MAX),
      );

      if ($x509 === false || !openssl_x509_export($x509, $certificatePem)) {
        throw new CertificateException('Unable to self-sign the certificate: ' . self::lastOpenSslError());
      }

      if (!openssl_pkey_export($key, $privateKeyPem, null, $configuration)) {
        throw new CertificateException('Unable to export the generated private key: ' . self::lastOpenSslError());
      }

      return Certificate::fromPem($certificatePem, $privateKeyPem);
    } catch (CertificateException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      throw new CertificateException('Unable to generate a self-signed certificate.', previous: $exception);
    } finally {
      $files->cleanup();
    }
  }

  private static function lastOpenSslError(): string
  {
    $last = 'unknown OpenSSL error';

    while (($error = openssl_error_string()) !== false) {
      $last = $error;
    }

    return $last;
  }
}
