<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class SignatureOptions
{
  private ?string $signerName          = null;
  private ?string $reason              = null;
  private ?string $location            = null;
  private ?string $contactInfo         = null;
  private ?DateTimeImmutable $signedAt = null;
  private ?string $fieldName           = null;
  private int $signatureCapacity       = 16384;

  public static function make(): self
  {
    return new self();
  }

  public function signer(?string $value): self
  {
    $this->signerName = self::filled($value);

    return $this;
  }

  public function reason(?string $value): self
  {
    $this->reason = self::filled($value);

    return $this;
  }

  public function location(?string $value): self
  {
    $this->location = self::filled($value);

    return $this;
  }

  public function contactInfo(?string $value): self
  {
    $this->contactInfo = self::filled($value);

    return $this;
  }

  public function signedAt(?DateTimeInterface $value): self
  {
    $this->signedAt = $value === null ? null : DateTimeImmutable::createFromInterface($value);

    return $this;
  }

  public function fieldName(?string $value): self
  {
    $this->fieldName = self::filled($value);

    return $this;
  }

  public function signatureCapacity(int $bytes): self
  {
    if ($bytes < 4096) {
      throw new InvalidArgumentException('Signature capacity must be at least 4096 bytes.');
    }

    $this->signatureCapacity = $bytes;

    return $this;
  }

  public function signerName(): ?string
  {
    return $this->signerName;
  }

  public function reasonValue(): ?string
  {
    return $this->reason;
  }

  public function locationValue(): ?string
  {
    return $this->location;
  }

  public function contactInfoValue(): ?string
  {
    return $this->contactInfo;
  }

  public function signingTime(): DateTimeImmutable
  {
    return $this->signedAt ?? new DateTimeImmutable();
  }

  public function fieldNameValue(): ?string
  {
    return $this->fieldName;
  }

  public function signatureCapacityBytes(): int
  {
    return $this->signatureCapacity;
  }

  private static function filled(?string $value): ?string
  {
    $value = $value === null ? null : trim($value);

    return $value === '' ? null : $value;
  }
}
