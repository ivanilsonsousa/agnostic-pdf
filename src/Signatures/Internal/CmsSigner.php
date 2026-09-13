<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures\Internal;

use AgnosticPDF\Exceptions\PdfSignatureException;
use AgnosticPDF\Signatures\Certificate;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use Throwable;

final class CmsSigner
{
  public function sign(string $content, Certificate $certificate): string
  {
    $files = new SecureTemporaryFiles();

    try {
      $input  = $files->write($content, 'agnostic_pdf_content_');
      $output = $files->empty('agnostic_pdf_cms_');
      $x509   = openssl_x509_read($certificate->certificatePem());
      $key    = openssl_pkey_get_private($certificate->privateKeyPem());

      if (!$x509 instanceof OpenSSLCertificate || !$key instanceof OpenSSLAsymmetricKey) {
        throw new PdfSignatureException('OpenSSL could not load the signing certificate.');
      }

      $chainPath = null;

      if ($certificate->chainPem() !== []) {
        $chainPath = $files->write(implode("\n", $certificate->chainPem()), 'agnostic_pdf_chain_');
      }

      $signed = @openssl_cms_sign(
        $input,
        $output,
        $x509,
        $key,
        [],
        OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY,
        OPENSSL_ENCODING_DER,
        $chainPath,
      );

      if (!$signed) {
        throw new PdfSignatureException('OpenSSL could not create the detached CMS signature.');
      }

      $cms = file_get_contents($output);

      if (!is_string($cms) || $cms === '') {
        throw new PdfSignatureException('OpenSSL returned an empty CMS signature.');
      }

      return $cms;
    } catch (PdfSignatureException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      throw new PdfSignatureException('Unable to create the detached CMS signature.', 0, $exception);
    } finally {
      $files->cleanup();
    }
  }
}
