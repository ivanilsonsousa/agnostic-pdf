<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures;

use AgnosticPDF\Exceptions\CertificateException;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CertificateInfo
{
  /**
   * @param array<string, mixed> $subject
   * @param array<string, mixed> $issuer
   */
  public function __construct(
    public array $subject,
    public array $issuer,
    public string $serialNumber,
    public DateTimeImmutable $validFrom,
    public DateTimeImmutable $validTo,
    public string $signatureAlgorithm,
    public bool $selfSigned,
    public string $pem,
  ) {
  }

  public static function fromPem(string $pem): self
  {
    $parsed = openssl_x509_parse($pem, false);

    if (!is_array($parsed)) {
      throw new CertificateException('Unable to parse the signer certificate.');
    }

    /** @var array<string, mixed> $subject */
    $subject = (array) ($parsed['subject'] ?? []);

    /** @var array<string, mixed> $issuer */
    $issuer = (array) ($parsed['issuer'] ?? []);

    return new self(
      subject: $subject,
      issuer: $issuer,
      serialNumber: (string) ($parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? ''),
      validFrom: self::date((int) ($parsed['validFrom_time_t'] ?? 0)),
      validTo: self::date((int) ($parsed['validTo_time_t'] ?? 0)),
      signatureAlgorithm: (string) ($parsed['signatureTypeSN'] ?? $parsed['signatureTypeLN'] ?? 'unknown'),
      selfSigned: $subject === $issuer,
      pem: $pem,
    );
  }

  public function isValidAt(DateTimeImmutable $instant): bool
  {
    return $instant >= $this->validFrom && $instant <= $this->validTo;
  }

  public function subjectName(): string
  {
    return self::name($this->subject);
  }

  public function issuerName(): string
  {
    return self::name($this->issuer);
  }

  private static function date(int $timestamp): DateTimeImmutable
  {
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
  }

  /** @param array<string, mixed> $name */
  private static function name(array $name): string
  {
    $parts = [];

    foreach ($name as $key => $value) {
      foreach ((array) $value as $item) {
        $parts[] = $key . '=' . (string) $item;
      }
    }

    return implode(', ', $parts);
  }
}
