<?php

declare(strict_types=1);

namespace AgnosticPDF\Services;

use AgnosticPDF\Contracts\PDFServiceInterface;
use Illuminate\Contracts\Container\Container;
use AgnosticPDF\Drivers\DompdfDriver;
use AgnosticPDF\Drivers\MPDFDriver;

class PDFManagerService
{
  public function __construct(private Container $container) {}

  public function pdf(): PDFServiceInterface
  {
    // sempre resolve conforme config atual
    return $this->container->make(PDFServiceInterface::class);
  }

  public function cloner(): PDFClonerService
  {
    return $this->container->make(PDFClonerService::class);
  }

  public function compressor(): PDFCompressor
  {
    return $this->container->make(PDFCompressor::class);
  }

  public function builder(): PDFBuilderService
  {
    $driverType = config('pdf.driver', 'mpdf');

    $driver = match ($driverType) {
      'dompdf' => new DompdfDriver(config('pdf.dompdf', [])),
      default  => new MPDFDriver(config('pdf.mpdf', [])),
    };

    $pdfService       = new PDFService($driver);
    $pdfClonerService = new PDFClonerService($driver);

    return new PDFBuilderService($pdfService, $pdfClonerService);
  }
}
