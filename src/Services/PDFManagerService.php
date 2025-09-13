<?php

namespace AgnosticPDF\Services;

use AgnosticPDF\Contracts\PDFServiceInterface;

class PDFManagerService
{
  protected ?PDFBuilderService $builderInstance = null;

  public function __construct(
        protected PDFServiceInterface $pdfService,
        protected PDFClonerService $pdfClonerService,
        protected PDFCompressor $pdfCompressor,
    ) {
  }

  public function pdf(): PDFServiceInterface
  {
    return $this->pdfService;
  }

  public function cloner(): PDFClonerService
  {
    return $this->pdfClonerService;
  }

  public function compressor(): PDFCompressor
  {
    return $this->pdfCompressor;
  }

  public function builder(): PDFBuilderService
  {
    return new PDFBuilderService($this->pdfService, $this->pdfClonerService);
  }
}
