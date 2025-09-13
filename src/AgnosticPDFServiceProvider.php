<?php

declare(strict_types=1);

namespace AgnosticPDF;

use AgnosticPDF\Contracts\PDFClonerDriverInterface;
use AgnosticPDF\Contracts\PDFServiceInterface;
use AgnosticPDF\Drivers\DompdfDriver;
use AgnosticPDF\Drivers\MPDFDriver;
use AgnosticPDF\Services\PDFClonerService;
use AgnosticPDF\Services\PDFCompressor;
use AgnosticPDF\Services\PDFManagerService;
use AgnosticPDF\Services\PDFService;
use Illuminate\Support\ServiceProvider;

final class AgnosticPDFServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    // config/pdf.php
    $this->mergeConfigFrom(__DIR__ . '/../config/pdf.php', 'pdf');

    // PDFServiceInterface -> driver atual (mpdf|dompdf)
    $this->app->bind(PDFServiceInterface::class, function () {
      return $this->createPdfDriver();
    });

    // Serviço fino que delega p/ driver
    $this->app->bind(PDFService::class, function ($app) {
      return new PDFService($app->make(PDFServiceInterface::class));
    });

    // Cloner: só MPDF suporta. Se pedir cloner em dompdf, lança erro no momento da resolução.
    $this->app->bind(PDFClonerDriverInterface::class, function () {
      return $this->createClonerDriver();
    });

    $this->app->bind(PDFClonerService::class, function ($app) {
      return new PDFClonerService($app->make(PDFClonerDriverInterface::class));
    });

    $this->app->singleton(PDFCompressor::class);

    $this->app->singleton(PDFManagerService::class, function ($app) {
      return new PDFManagerService(
        $app->make(PDFService::class),
        $app->make(PDFClonerService::class),
        $app->make(PDFCompressor::class),
      );
    });
  }

  public function boot(): void
  {
    $this->publishes([
      __DIR__ . '/../config/pdf.php' => config_path('pdf.php'),
    ], 'pdf-config');
  }

  // ---------- Factories ----------

  /** Driver de renderização atual (mpdf|dompdf) */
  private function createPdfDriver(): PDFServiceInterface
  {
    $driver = config('pdf.driver', 'mpdf');

    return match ($driver) {
      'mpdf'   => new MPDFDriver(config('pdf.mpdf', [])),
      'dompdf' => new DompdfDriver(config('pdf.dompdf', [])),
      default  => new MPDFDriver(config('pdf.mpdf', [])),
    };
  }

  /** Driver de clonagem (somente MPDF suporta) */
  private function createClonerDriver(): PDFClonerDriverInterface
  {
    $driver = config('pdf.driver', 'mpdf');

    return match ($driver) {
      'mpdf'  => new MPDFDriver(config('pdf.mpdf', [])),
      default => throw new \RuntimeException(
        sprintf('Clonagem de PDF não é suportada pelo driver atual (%s).', $driver)
      ),
    };
  }
}
