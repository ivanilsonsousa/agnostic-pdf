<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures\Internal;

final readonly class PdfSignatureRecord
{
  /** @param array{int, int, int, int} $byteRange */
  public function __construct(
    public string $fieldName,
    public array $byteRange,
    public string $cms,
    public ?string $signedAt,
    public ?string $signerName,
    public ?string $reason,
    public ?string $location,
  ) {
  }
}
