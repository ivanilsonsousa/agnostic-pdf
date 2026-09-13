<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures\Internal;

use AgnosticPDF\Signatures\TrustStore;
use Throwable;

final class CmsVerifier
{
  public function verify(string $cms, string $content, ?TrustStore $trustStore): CmsVerification
  {
    $files = new SecureTemporaryFiles();

    try {
      $cmsPath     = $files->write($cms, 'agnostic_pdf_verify_cms_');
      $contentPath = $files->write($content, 'agnostic_pdf_verify_content_');
      $signersPath = $files->empty('agnostic_pdf_verify_cert_');

      $valid = @openssl_cms_verify(
        $contentPath,
        OPENSSL_CMS_BINARY | OPENSSL_CMS_DETACHED | OPENSSL_CMS_NOVERIFY,
        $signersPath,
        [],
        null,
        null,
        null,
        $cmsPath,
        OPENSSL_ENCODING_DER,
      );

      $certificatePem = $valid ? file_get_contents($signersPath) : false;
      $trusted        = null;

      if ($valid && $trustStore !== null) {
        $trustPath = $files->write($trustStore->pem(), 'agnostic_pdf_trust_');
        $trusted   = @openssl_cms_verify(
          $contentPath,
          OPENSSL_CMS_BINARY | OPENSSL_CMS_DETACHED,
          null,
          [$trustPath],
          null,
          null,
          null,
          $cmsPath,
          OPENSSL_ENCODING_DER,
        );
      }

      return new CmsVerification(
        valid: $valid,
        trusted: $trusted,
        certificatePem: is_string($certificatePem) && $certificatePem !== '' ? $certificatePem : null,
        error: $valid ? null : 'CMS signature verification failed.',
      );
    } catch (Throwable) {
      return new CmsVerification(false, null, null, 'CMS signature verification failed.');
    } finally {
      $files->cleanup();
    }
  }
}
