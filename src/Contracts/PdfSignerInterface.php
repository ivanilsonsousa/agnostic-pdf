<?php

declare(strict_types=1);

namespace AgnosticPDF\Contracts;

use AgnosticPDF\Signatures\Certificate;
use AgnosticPDF\Signatures\SignatureAppearance;
use AgnosticPDF\Signatures\SignatureOptions;
use AgnosticPDF\Signatures\TrustStore;
use AgnosticPDF\Signatures\VerificationResult;

interface PdfSignerInterface
{
  /**
   * Appends a detached CMS signature to a PDF without rewriting its existing bytes.
   */
  public function sign(
    string $input,
    Certificate $certificate,
    ?SignatureAppearance $appearance = null,
    ?SignatureOptions $options = null,
  ): string;

  /**
   * Verifies every embedded approval signature found in a PDF.
   */
  public function verify(string $input, ?TrustStore $trustStore = null): VerificationResult;
}
