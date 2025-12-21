<?php

namespace AgnosticPDF\Services;

use AgnosticPDF\Contracts\PDFServiceInterface;

class PDFService implements PDFServiceInterface
{
  protected PDFServiceInterface $driver;

  public function __construct(PDFServiceInterface $driver)
  {
    $this->driver = $driver;
  }

  public function loadHtml(string $html): self
  {
    $this->driver->loadHtml($html);

    return $this;
  }

  public function loadView(string $view, array $data = []): self
  {
    $this->driver->loadView($view, $data);

    return $this;
  }
  
  public function addPage(array $config = []): self
  {
    $this->driver->addPage($config);

    return $this;
  }

  public function output(): string
  {
    return $this->driver->output();
  }

  public function download(string $filename): void
  {
    $this->driver->download($filename);
  }

  public function save(string $path): void
  {
    $this->driver->save($path);
  }

  public function stream(string $filename): void
  {
    $this->driver->stream($filename);
  }

  public function streamResponse(string $filename): \Illuminate\Http\Response
  {
    return $this->driver->streamResponse($filename);
  }

  public function getDriver()
  {
    return $this->driver;
  }
  
  public function getEngine()
  {
    return $this->driver->getEngine();
  }

  public function tap(callable $fn): void
  {
    $this->driver->tap($fn);
  }
}
