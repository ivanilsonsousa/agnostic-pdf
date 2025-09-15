<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Contracts\PDFClonerDriverInterface;
use AgnosticPDF\Contracts\PDFServiceInterface;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

final class PDFCoverPlusImagesTest extends TestCase
{
  public function test_cover_then_image_pages_with_same_instance_mpdf(): void
  {
    // Clonagem é MPDF
    config()->set('pdf.driver', 'mpdf');

    // A view minimalista deve existir
    $this->assertTrue(View::exists('pdf.examples.image_only'), 'A view pdf.examples.image_only não foi encontrada.');

    // Assets
    $examplesDir = realpath(__DIR__ . '/../Examples');
    $this->assertNotFalse($examplesDir, 'Diretório tests/Examples não encontrado.');

    $coverPath = $examplesDir . '/book-cover.pdf';
    $img1      = $examplesDir . '/minimalist-1.jpg';
    $img2      = $examplesDir . '/minimalist-2.jpg';
    $img3      = $examplesDir . '/minimalist-3.jpg';
    $img4      = $examplesDir . '/minimalist-4.jpg';

    foreach ([$coverPath, $img1, $img2, $img3, $img4] as $p) {
      $this->assertFileExists($p, "Arquivo de exemplo ausente: {$p}");
    }

    /** @var PDFClonerDriverInterface $cloner */
    $cloner = $this->app->make(PDFClonerDriverInterface::class);

    // Prepara e clona SOMENTE a primeira página da capa
    $pageCount = $cloner->prepareClone($coverPath);
    $this->assertGreaterThanOrEqual(1, $pageCount, 'A capa deveria ter pelo menos 1 página.');
    $cloner->clonePage(1);

    // Usa a mesma instância como serviço de PDF
    $this->assertInstanceOf(PDFServiceInterface::class, $cloner);

    /** @var PDFServiceInterface $svc */
    $svc = $cloner;

    $images = [$img1, $img2, $img3, $img4];

    // Para cada imagem, gera UMA página
    foreach ($images as $path) {
      $html = View::make('pdf.examples.image_only', [
        // caminho absoluto com file:// para MPDF carregar a imagem do FS local
        'image' => 'file://' . realpath($path),
      ])->render();

      $svc->loadHtml($html); // MPDF append
    }

    $outPath = $this->outputDir() . '/cover-plus-images-mpdf.pdf';
    $svc->save($outPath);

    $this->assertFileExists($outPath);
    $this->assertGreaterThan(40_000, filesize($outPath), 'PDF resultante muito pequeno.');

    // Deve ter: 1 (capa) + 4 (imagens) = 5 páginas
    $pagsGeradas = $this->countPdfPages($outPath);
    $this->assertSame(5, $pagsGeradas, "Esperado 5 páginas (1 capa + 4 imagens), mas gerou {$pagsGeradas}.");
  }

  /**
   * Contagem simples de páginas no PDF lendo /Type /Page (não /Pages).
   */
  private function countPdfPages(string $pdfPath): int
  {
    $content = @file_get_contents($pdfPath);

    if ($content === false) {
      return 0;
    }

    if (preg_match_all('/\/Type\s*\/Page(?!s)\b/', $content, $m)) {
      return count($m[0]);
    }

    return 0;
  }
}
