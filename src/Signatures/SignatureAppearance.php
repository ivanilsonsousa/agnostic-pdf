<?php

declare(strict_types=1);

namespace AgnosticPDF\Signatures;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class SignatureAppearance
{
  private int $pageNumber              = 1;
  private float $x                     = 0.0;
  private float $y                     = 0.0;
  private float $width                 = 200.0;
  private float $height                = 60.0;
  private ?string $text                = null;
  private ?string $image               = null;
  private ?string $pdf                 = null;
  private ?string $signerName          = null;
  private ?DateTimeImmutable $signedAt = null;
  private ?string $reason              = null;
  private ?string $location            = null;

  public static function make(): self
  {
    return new self();
  }

  public static function default(
    string $signerName,
    ?DateTimeInterface $signedAt = null,
    ?string $reason = null,
    ?string $location = null,
  ): self {
    return (new self())
      ->signer($signerName)
      ->date($signedAt ?? new DateTimeImmutable())
      ->reason($reason)
      ->location($location);
  }

  public function page(int $pageNumber): self
  {
    if ($pageNumber !== -1 && $pageNumber < 1) {
      throw new InvalidArgumentException('Signature page must be one-based or -1 for the last page.');
    }

    $this->pageNumber = $pageNumber;

    return $this;
  }

  public function position(float $x, float $y): self
  {
    if ($x < 0 || $y < 0) {
      throw new InvalidArgumentException('Signature coordinates cannot be negative.');
    }

    $this->x = $x;
    $this->y = $y;

    return $this;
  }

  public function size(float $width, float $height): self
  {
    if ($width <= 0 || $height <= 0) {
      throw new InvalidArgumentException('Signature appearance dimensions must be positive.');
    }

    $this->width  = $width;
    $this->height = $height;

    return $this;
  }

  public function text(?string $text): self
  {
    $this->text = self::filled($text);

    return $this;
  }

  /**
   * Uses a complete PNG/JPEG as the seal. The argument may be bytes or a local path.
   */
  public function image(?string $image): self
  {
    if ($image === null || $image === '') {
      $this->image = null;

      return $this;
    }

    $bytes = is_file($image) ? @file_get_contents($image) : $image;
    $info  = is_string($bytes) ? @getimagesizefromstring($bytes) : false;

    if (!is_string($bytes) || !is_array($info) || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png'], true)) {
      throw new InvalidArgumentException('Signature appearance image must be a valid PNG or JPEG.');
    }

    $this->image = $bytes;
    $this->pdf   = null;

    return $this;
  }

  /**
   * Uses the first page of a PDF as a complete vector appearance.
   *
   * The argument may be PDF bytes or a local path.
   */
  public function pdf(?string $pdf): self
  {
    if ($pdf === null || $pdf === '') {
      $this->pdf = null;

      return $this;
    }

    $bytes = is_file($pdf) ? @file_get_contents($pdf) : $pdf;

    if (!is_string($bytes) || !str_starts_with(ltrim($bytes), '%PDF-')) {
      throw new InvalidArgumentException('Signature appearance PDF must be a valid PDF document.');
    }

    $this->pdf   = $bytes;
    $this->image = null;

    return $this;
  }

  public function signer(?string $name): self
  {
    $this->signerName = self::filled($name);

    return $this;
  }

  public function date(?DateTimeInterface $date): self
  {
    $this->signedAt = $date === null ? null : DateTimeImmutable::createFromInterface($date);

    return $this;
  }

  public function reason(?string $reason): self
  {
    $this->reason = self::filled($reason);

    return $this;
  }

  public function location(?string $location): self
  {
    $this->location = self::filled($location);

    return $this;
  }

  public function pageNumber(): int
  {
    return $this->pageNumber;
  }

  /** @return array{float, float, float, float} */
  public function rectangle(): array
  {
    return [$this->x, $this->y, $this->width, $this->height];
  }

  public function imageBytes(): ?string
  {
    return $this->image;
  }

  public function pdfBytes(): ?string
  {
    return $this->pdf;
  }

  /** @return list<string> */
  public function lines(): array
  {
    if ($this->text !== null) {
      return preg_split('/\R/u', $this->text) ?: [$this->text];
    }

    if (($this->image !== null || $this->pdf !== null)
      && $this->signerName === null
      && $this->signedAt   === null
      && $this->reason     === null
      && $this->location   === null
    ) {
      return [];
    }

    $lines = ['Documento assinado digitalmente'];

    if ($this->signerName !== null) {
      $lines[] = $this->signerName;
    }

    if ($this->signedAt !== null) {
      $lines[] = 'Data: ' . $this->signedAt->format('d/m/Y H:i:s P');
    }

    if ($this->reason !== null) {
      $lines[] = 'Motivo: ' . $this->reason;
    }

    if ($this->location !== null) {
      $lines[] = 'Local: ' . $this->location;
    }

    return $lines;
  }

  private static function filled(?string $value): ?string
  {
    $value = $value === null ? null : trim($value);

    return $value === '' ? null : $value;
  }
}
