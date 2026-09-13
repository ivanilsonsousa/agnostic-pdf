<?php

declare(strict_types=1);

namespace AgnosticPDF\Contracts;

use AgnosticPDF\Signatures\Certificate;
use AgnosticPDF\Signatures\CertificateRequest;

/**
 * Extension point for a future internal CA. The first implementation is self-signed.
 */
interface CertificateIssuerInterface
{
  public function issue(CertificateRequest $request): Certificate;
}
