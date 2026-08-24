<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Services\PDFService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PDFHtmlAndSaveTest extends TestCase
{
  public static function drivers(): array
  {
    return [
      ['mpdf'],
      ['dompdf'],
    ];
  }

  #[DataProvider('drivers')]
  public function test_it_renders_html_and_saves_pdf(string $driver): void
  {
    config()->set('pdf.driver', $driver);

    /** @var PDFService $pdf */
    $pdf = $this->app->make(PDFService::class);

    $html = <<<HTML
            <html>
              <head><meta charset="utf-8"></head>
              <body>
                <h1>Olá {$driver}!</h1>
                <p>PDF gerado em integração.</p>
              </body>
            </html>
        HTML;

    $outPath = $this->outputDir() . "/html-save-{$driver}.pdf";

    $pdf->loadHtml($html)->save($outPath);

    $this->assertFileExists($outPath);
    $this->assertGreaterThan(100, filesize($outPath));
  }

  #[DataProvider('drivers')]
  public function test_it_adds_a_page(string $driver): void
  {
    config()->set('pdf.driver', $driver);

    /** @var PDFService $pdf */
    $pdf = $this->app->make(PDFService::class);

    $content = $pdf
      ->loadHtml('<p>Primeira página</p>')
      ->addPage()
      ->loadHtml('<p>Segunda página</p>')
      ->output();

    $this->assertStringStartsWith('%PDF-', $content);
    $this->assertGreaterThanOrEqual(2, preg_match_all('/\/Type\s*\/Page\b/', $content));
  }
}
