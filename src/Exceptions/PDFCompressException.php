<?php

namespace AgnosticPDF\Exceptions;

use RuntimeException;

class PDFCompressException extends RuntimeException
{
  private array $context;

  public function __construct(string $message, array $context = [], int $code = 0, ?\Throwable $previous)
  {
    parent::__construct($message, $code, $previous);
    $this->context = $context;
  }

  public function getContext(): array
  {
    return $this->context;
  }
}
