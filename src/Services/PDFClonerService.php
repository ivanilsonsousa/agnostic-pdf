<?php

namespace AgnosticPDF\Services;

use AgnosticPDF\Contracts\PDFClonerDriverInterface;

class PDFClonerService
{
  private PDFClonerDriverInterface $driver;

  public function __construct(PDFClonerDriverInterface $driver)
  {
    $this->driver = $driver;
  }

  private function runWithOptionalCompression(string $pathFile, bool $force, callable $operation): self
  {
    $forceCompress = $force && config('pdf.force_compress_on_clone', false);

    try {
      return $operation($pathFile);
    } catch (\Throwable $e) {
      if (!$forceCompress) {
        throw $e;
      }

      $pdfCompressor = app(PDFCompressor::class);
      $compressed    = $pdfCompressor->reduce($pathFile);

      return $operation($compressed);
    }
  }

  public function cloneFromFile(string $file, ?callable $callback = null, bool $force = true): self
  {
    return $this->runWithOptionalCompression(
      $file,
      $force,
      fn(string $pathFile) => $this->tryClone($pathFile, $callback)
    );
  }

  public function eachPageFromFile(string $file, ?callable $callback = null, bool $force = true): self
  {
    return $this->runWithOptionalCompression(
      $file,
      $force,
      fn(string $pathFile) => $this->eachPage($pathFile, $callback)
    );
  }

  private function tryClone(string $pathFile, ?callable $callback = null): self
  {
    $pageCount = $this->driver->prepareClone($pathFile);

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
      $this->driver->clonePage($pageNo);

      if ($callback) {
        $callback($this->driver, $pageNo, $pageCount);
      }
    }

    return $this;
  }

  /**
   * Itera as páginas de um PDF chamando $callback por página.
   *
   * Callback: function(PDFClonerDriverInterface $driver, int $pageNo, int $pageCount): void
   */
  public function eachPage(string $pathFile, ?callable $callback = null, bool $force = true): self
  {
    $pageCount = $this->driver->prepareClone($pathFile);

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
      $this->driver->importPageDefinitions($pageNo);

      $callback($this->driver, $pageNo, $pageCount);
    }

    return $this;
  }
}
