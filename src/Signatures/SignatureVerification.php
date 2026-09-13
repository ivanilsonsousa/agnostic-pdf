<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures;

use DateTimeImmutable;

final readonly class SignatureVerification
{
  public function __construct(
    public int $index,
    public string $fieldName,
    public ?string $signerName,
    public ?string $reason,
    public ?string $location,
    public bool $cryptographicallyValid,
    public bool $documentIntegrityValid,
    public ?bool $certificateTrusted,
    public ?CertificateInfo $certificate,
    public ?DateTimeImmutable $signedAt,
    public bool $certificateValidAtSigningTime,
    public string $algorithm,
    public bool $coversRevision,
    public int $revisionLength,
    public bool $hasLaterChanges,
    public ?string $error = null,
  ) {
  }
}
