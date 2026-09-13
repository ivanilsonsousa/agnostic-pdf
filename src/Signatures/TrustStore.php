<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures;

use AgnosticPDF\Exceptions\CertificateException;

final readonly class TrustStore
{
  private function __construct(private string $pem)
  {
    if (!str_contains($pem, '-----BEGIN CERTIFICATE-----')) {
      throw new CertificateException('The trust store does not contain a PEM certificate.');
    }
  }

  public static function fromPem(string $pem): self
  {
    return new self($pem);
  }

  public static function fromFile(string $path): self
  {
    $pem = @file_get_contents($path);

    if ($pem === false) {
      throw new CertificateException("Unable to read trust store: {$path}");
    }

    return new self($pem);
  }

  public function pem(): string
  {
    return $this->pem;
  }
}
