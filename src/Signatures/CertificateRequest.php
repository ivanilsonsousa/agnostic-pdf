<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures;

final readonly class CertificateRequest
{
  /**
   * @param array<string, string> $distinguishedName
   */
  public function __construct(
    public array $distinguishedName,
    public int $validDays = 365,
    public int $keyBits = 2048,
    public string $digestAlgorithm = 'sha256',
    public ?int $serialNumber = null,
  ) {
  }
}
