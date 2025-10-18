<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Drivers\MPDFDriver;
use AgnosticPDF\Services\PDFBuilderService;
use AgnosticPDF\Services\PDFManagerService;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PDFAndImagesTest extends TestCase
{
  public static function drivers(): array
  {
    return [
      ['mpdf'],
      // ['dompdf'], // Dompdf não suporta clone/import de PDFs (addFile)
    ];
  }

  #[DataProvider('drivers')]
  public function test_merge_pdf_and_images_with_callbacks(string $driver): void
  {
    // Usa mpdf
    config()->set('pdf.driver', $driver);

    /** @var PDFBuilderService $builder */
    // $builder = $this->app->make(PDFBuilderService::class); //not working because of dependencies
    $manager = $this->app->make(PDFManagerService::class);
    $builder = $manager->builder();

    $examplesDir = realpath(__DIR__ . '/../Examples');
    $outPath  = $this->outputDir() . "/merge-pdf-images-{$driver}.pdf";

    // arquivos de exemplo
    $pdfPath   = $examplesDir . '/sample-1.pdf';
    $imgPath1  = $examplesDir . '/minimalist-1.jpg';
    $imgPath2  = $examplesDir . '/minimalist-2.jpg';

    $this->assertFileExists($pdfPath, 'sample-1.pdf não encontrado em Examples/');
    $this->assertFileExists($imgPath1, 'minimalist-1.jpg não encontrado em Examples/');
    $this->assertFileExists($imgPath2, 'minimalist-2.jpg não encontrado em Examples/');

    // HTML simples de “carimbo” (overlay)
    $stampHTML = $this->makeStampHtml('AG-TEST ' . Str::upper($driver));

    $builder->addView('pdf.examples.cover', [
      'title'       => 'Composite PDF - Cover',
      'subtitle'    => 'Functional test with builder()',
      'generatedAt' => now()->format('d/m/Y H:i:s'),
    ]);

    // 1) adiciona páginas do PDF com callback (sobrepõe carimbo)
    $builder->addFile($pdfPath, function (MPDFDriver $driver) use ($stampHTML) {
      $driver->writeHtml($stampHTML);
    });

    // 2) adiciona imagem 1 com callback (sobrepõe carimbo)
    $builder->addImage($imgPath1, [], function (MPDFDriver $driver) use ($stampHTML) {
      $driver->writeHtml($stampHTML);
    });

    // 3) adiciona imagem 2 com callback (sobrepõe carimbo)
    $builder->addImage($imgPath2, [], function (MPDFDriver $driver) use ($stampHTML) {
      $driver->writeHtml($stampHTML);
    });

    // gera saída
    $builder->save($outPath);

    // Asserções
    $this->assertFileExists($outPath);
    // $this->assertGreaterThan(10_000, filesize($outPath), 'Arquivo final muito pequeno (esperado > 10KB)');
  }

  private function makeStampHtml(string $text): string
  {
    // bloco simples posicionado no canto inferior-direito
    return <<<HTML
      <div style="position: absolute; right: 20px; bottom: 20px; font-size: 10pt; font-family: sans-serif; color: #333; background: rgba(255,255,255,0.7); padding: 6px 10px; border-radius: 4px;">
        {$text}
      </div>
    HTML;
  }
}
