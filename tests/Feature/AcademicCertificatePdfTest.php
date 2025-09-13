<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Services\PDFService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Academic\CertificateFactory;
use Tests\TestCase;

final class AcademicCertificatePdfTest extends TestCase
{
  public static function drivers(): array
  {
    return [
      ['mpdf'],
      ['dompdf'],
    ];
  }

  #[DataProvider('drivers')]
  public function test_certificate_pdf_is_rendered_with_blade_and_saved(string $driver): void
  {
    config()->set('pdf.driver', $driver);

    $factory = new CertificateFactory();
    $data    = $factory->makeData();

    /** @var PDFService $pdf */
    $pdf     = $this->app->make(PDFService::class);
    $outPath = $this->outputDir() . "/certificado-{$driver}.pdf";

    $pdf->loadView('pdf.academic.certificate', $data)->save($outPath);

    $this->assertFileExists($outPath);
    $this->assertGreaterThan(5_000, filesize($outPath));
  }
}
