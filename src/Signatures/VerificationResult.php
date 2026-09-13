<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures;

final readonly class VerificationResult
{
  /** @param list<SignatureVerification> $signatures */
  public function __construct(public array $signatures)
  {
  }

  public function count(): int
  {
    return count($this->signatures);
  }

  public function isSigned(): bool
  {
    return $this->signatures !== [];
  }

  public function allCryptographicallyValid(): bool
  {
    if (!$this->isSigned()) {
      return false;
    }

    foreach ($this->signatures as $signature) {
      if (!$signature->cryptographicallyValid) {
        return false;
      }
    }

    return true;
  }

  public function allTrusted(): ?bool
  {
    if (!$this->isSigned()) {
      return null;
    }

    foreach ($this->signatures as $signature) {
      if ($signature->certificateTrusted === null) {
        return null;
      }

      if (!$signature->certificateTrusted) {
        return false;
      }
    }

    return true;
  }
}
