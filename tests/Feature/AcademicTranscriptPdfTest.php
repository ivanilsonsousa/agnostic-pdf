<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Services\PDFService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Academic\TranscriptFactory;
use Tests\TestCase;

final class AcademicTranscriptPdfTest extends TestCase
{
  public static function drivers(): array
  {
    return [
      ['mpdf'],
      ['dompdf'],
    ];
  }

  #[DataProvider('drivers')]
  public function test_transcript_pdf_is_rendered_with_blade_and_saved(string $driver): void
  {
    config()->set('pdf.driver', $driver);

    $factory = new TranscriptFactory();
    $data    = $factory->makeData();

    /** @var PDFService $pdf */
    $pdf     = $this->app->make(PDFService::class);
    $outPath = $this->outputDir() . "/historico-{$driver}.pdf";

    $pdf->loadView('pdf.academic.transcript', $data)->save($outPath);

    $this->assertFileExists($outPath);
    $this->assertGreaterThan(8_000, filesize($outPath));
  }
}
