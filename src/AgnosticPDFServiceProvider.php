<?php

declare(strict_types=1);

namespace AgnosticPDF;

use AgnosticPDF\Contracts\PDFClonerDriverInterface;
use AgnosticPDF\Contracts\PDFServiceInterface;
use Illuminate\Contracts\Container\Container;
use AgnosticPDF\Services\PDFManagerService;
use AgnosticPDF\Services\PDFClonerService;
use AgnosticPDF\Services\PDFCompressor;
use Illuminate\Support\ServiceProvider;
use AgnosticPDF\Drivers\DompdfDriver;
use AgnosticPDF\Services\PDFService;
use AgnosticPDF\Drivers\MPDFDriver;

final class AgnosticPDFServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    // config/pdf.php
    $this->mergeConfigFrom(__DIR__ . '/../config/pdf.php', 'pdf');

    // Resolve o serviço "fino" já com o driver escolhido
    $this->app->bind(PDFServiceInterface::class, function (): PDFServiceInterface {
      $driver = config('pdf.driver', 'mpdf');

      return match ($driver) {
        'dompdf' => new PDFService(new DompdfDriver(config('pdf.dompdf', []))),
        default  => new PDFService(new MPDFDriver(config('pdf.mpdf', []))),
      };
    });

    // Alias opcional para permitir type-hint em PDFService
    $this->app->alias(PDFServiceInterface::class, PDFService::class);

    // Cloner: sempre MPDF (único que suporta)
    $this->app->bind(PDFClonerDriverInterface::class, function () {
      return new MPDFDriver(config('pdf.mpdf', []));
    });

    $this->app->bind(PDFClonerService::class, function (Container $app) {
      return new PDFClonerService($app->make(PDFClonerDriverInterface::class));
    });

    // Stateless -> singleton
    $this->app->singleton(PDFCompressor::class, fn() => new PDFCompressor());

    // Manager: singleton e SEM reter instâncias de PDF; resolve sob demanda
    $this->app->singleton(PDFManagerService::class, function (Container $app) {
      return new PDFManagerService($app);
    });
  }

  public function boot(): void
  {
    $this->publishes([
      __DIR__ . '/../config/pdf.php' => config_path('pdf.php'),
    ], 'pdf-config');
  }
}
