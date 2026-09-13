<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures;

use AgnosticPDF\Contracts\CertificateIssuerInterface;
use InvalidArgumentException;

final class CertificateBuilder
{
  /** @var array<string, string> */
  private array $distinguishedName = [];

  private int $validDays          = 365;
  private int $keyBits            = 2048;
  private string $digestAlgorithm = 'sha256';
  private ?int $serialNumber      = null;

  public function __construct(
    private readonly CertificateIssuerInterface $issuer = new SelfSignedCertificateIssuer(),
  ) {
  }

  public function commonName(string $value): self
  {
    return $this->dn('commonName', $value);
  }

  public function organization(string $value): self
  {
    return $this->dn('organizationName', $value);
  }

  public function organizationalUnit(string $value): self
  {
    return $this->dn('organizationalUnitName', $value);
  }

  public function country(string $value): self
  {
    $value = strtoupper(trim($value));

    if (strlen($value) !== 2) {
      throw new InvalidArgumentException('Country must be a two-letter ISO code.');
    }

    return $this->dn('countryName', $value);
  }

  public function state(string $value): self
  {
    return $this->dn('stateOrProvinceName', $value);
  }

  public function locality(string $value): self
  {
    return $this->dn('localityName', $value);
  }

  public function email(string $value): self
  {
    if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
      throw new InvalidArgumentException('Invalid certificate email address.');
    }

    return $this->dn('emailAddress', $value);
  }

  public function validFor(int $days): self
  {
    if ($days < 1) {
      throw new InvalidArgumentException('Certificate validity must be at least one day.');
    }

    $this->validDays = $days;

    return $this;
  }

  public function keyBits(int $bits): self
  {
    if ($bits < 2048) {
      throw new InvalidArgumentException('RSA keys shorter than 2048 bits are not supported.');
    }

    $this->keyBits = $bits;

    return $this;
  }

  public function digestAlgorithm(string $algorithm): self
  {
    $algorithm = strtolower(trim($algorithm));

    if (!in_array($algorithm, ['sha256', 'sha384', 'sha512'], true)) {
      throw new InvalidArgumentException('Digest algorithm must be sha256, sha384 or sha512.');
    }

    $this->digestAlgorithm = $algorithm;

    return $this;
  }

  public function serialNumber(int $serialNumber): self
  {
    if ($serialNumber < 1) {
      throw new InvalidArgumentException('Certificate serial number must be positive.');
    }

    $this->serialNumber = $serialNumber;

    return $this;
  }

  public function generate(): Certificate
  {
    if (!isset($this->distinguishedName['commonName'])) {
      throw new InvalidArgumentException('A common name is required to generate a certificate.');
    }

    return $this->issuer->issue(new CertificateRequest(
      distinguishedName: $this->distinguishedName,
      validDays: $this->validDays,
      keyBits: $this->keyBits,
      digestAlgorithm: $this->digestAlgorithm,
      serialNumber: $this->serialNumber,
    ));
  }

  private function dn(string $key, string $value): self
  {
    $value = trim($value);

    if ($value === '') {
      throw new InvalidArgumentException('Certificate subject values cannot be empty.');
    }

    $this->distinguishedName[$key] = $value;

    return $this;
  }
}
