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

  public function cloneFromFile(string $file, ?callable $callback = null, bool $force = true): self
  {
    $forceCompress = $force && config('pdf.force_compress_on_clone', false);
    
    try {
      return $this->tryClone($file, $callback);
    } catch (\Throwable $e) {
      if (!$forceCompress) {
        throw $e;
      }

      $pdfCompressor = app(PDFCompressor::class);
      $compressed    = $pdfCompressor->reduce($file);

      return $this->tryClone($compressed, $callback);
    }
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
}
