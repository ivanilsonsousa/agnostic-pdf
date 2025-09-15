<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Contracts\PDFClonerDriverInterface;
use AgnosticPDF\Contracts\PDFServiceInterface;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

final class PDFCoverPlusTextTest extends TestCase
{
  public function test_cover_then_text_pages_with_same_instance_mpdf(): void
  {
    // Clonagem disponível só no MPDF
    config()->set('pdf.driver', 'mpdf');

    $this->assertTrue(View::exists('pdf.examples.text_only'), 'A view pdf.examples.text_only não foi encontrada.');

    // Arquivos de exemplo
    $examplesDir = realpath(__DIR__ . '/../Examples');
    $this->assertNotFalse($examplesDir, 'Diretório tests/Examples não encontrado.');

    $coverPath = $examplesDir . '/test-cover.pdf';
    $this->assertFileExists($coverPath, 'Arquivo de capa tests/Examples/test-cover.pdf não encontrado.');

    /** @var PDFClonerDriverInterface $cloner */
    $cloner = $this->app->make(PDFClonerDriverInterface::class);

    $pageCount = $cloner->prepareClone($coverPath);
    $this->assertGreaterThanOrEqual(1, $pageCount, 'A capa deveria ter pelo menos 1 página.');
    $cloner->clonePage(1);

    $this->assertInstanceOf(PDFServiceInterface::class, $cloner);

    /** @var PDFServiceInterface $svc */
    $svc = $cloner;

    $pages = [
      ['title' => 'Seção 1', 'text' => 'Este é um exemplo de página somente com texto. Um parágrafo curto para caber confortavelmente em uma página.'],
      ['title' => 'Seção 2', 'text' => 'Conteúdo textual adicional. Mantemos textos concisos para evitar quebra inesperada para a página seguinte.'],
      ['title' => 'Seção 3', 'text' => 'Mais um bloco de texto. A ideia é validar a concatenação de páginas após a capa clonada usando a mesma instância do driver.'],
      ['title' => 'Seção 4', 'text' => 'Página final de texto simples. Depois salvamos e verificamos a contagem total de páginas do PDF.'],
    ];

    foreach ($pages as $p) {
      $html = View::make('pdf.examples.text_only', $p)->render();
      $svc->loadHtml($html); // MPDF append
    }

    $outPath = $this->outputDir() . '/cover-plus-text-mpdf.pdf';
    $svc->save($outPath);

    $this->assertFileExists($outPath);
    // Texto puro costuma gerar PDFs menores; limiar mais baixo que imagens
    $this->assertGreaterThan(5_000, filesize($outPath), 'PDF resultante muito pequeno.');

    // Esperado: 1 (capa) + 4 (texto) = 5 páginas
    $pagsGeradas = $this->countPdfPages($outPath);
    $this->assertSame(5, $pagsGeradas, "Esperado 5 páginas (1 capa + 4 texto), mas gerou {$pagsGeradas}.");
  }

  /** Contagem simples de páginas via /Type /Page (não é parser completo, mas suficiente p/ teste). */
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
