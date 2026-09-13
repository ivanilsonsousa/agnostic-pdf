<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures\Internal;

final readonly class CmsVerification
{
  public function __construct(
    public bool $valid,
    public ?bool $trusted,
    public ?string $certificatePem,
    public ?string $error = null,
  ) {
  }
}
