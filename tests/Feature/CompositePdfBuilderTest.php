<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use AgnosticPDF\Services\PDFManagerService;
use Tests\TestCase;

final class CompositePdfBuilderTest extends TestCase
{
  public static function drivers(): array
  {
    return [
      ['mpdf'],
      // ['dompdf'],
    ];
  }

  #[DataProvider('drivers')]
  public function test_builder_adds_cover_then_two_pdfs(string $driver): void
  {
    // Define o driver (mpdf|dompdf)
    config()->set('pdf.driver', $driver);

    /** @var PDFManagerService $manager */
    $manager = $this->app->make(PDFManagerService::class);
    $builder = $manager->builder();

    // --- Capa via Blade ---
    $builder->addView('pdf.examples.cover', [
      'title'       => 'Composite PDF - Cover',
      'subtitle'    => 'Functional test with builder()',
      'generatedAt' => now()->format('d/m/Y H:i:s'),
    ]);

    // --- Anexos: dois PDFs ---
    $sample1 = $this->samplePath('sample-1.pdf');
    $sample2 = $this->samplePath('sample-2.pdf');

    // Garante que os arquivos de exemplo existem
    $this->assertFileExists($sample1, '/tests/ExempleFiles/sample-1.pdf not found');
    $this->assertFileExists($sample2, '/tests/ExempleFiles/sample-2.pdf not found');

    $builder->addFile($sample1);
    $builder->addFile($sample2);

    // --- Salva e valida ---
    $outPath = $this->outputDir() . "/composite-{$driver}.pdf";
    $builder->save($outPath);

    $this->assertFileExists($outPath);
    $this->assertGreaterThan(10_000, filesize($outPath), 'Composite PDF seems too small');
  }

  private function samplePath(string $filename): string
  {
    // tests/Feature -> sobe 1 nível para tests/, depois Example/ExempleFiles
    $candidates = [
      realpath(__DIR__ . "/../Examples/{$filename}"),
      realpath(__DIR__ . "/../Examples/{$filename}"),
    ];

    foreach ($candidates as $p) {
      if ($p !== false && is_file($p)) {
        return $p;
      }
    }

    $this->fail("File not found: " . __DIR__ . "/../Example(Exemple)Files/{$filename}");
  }
}
