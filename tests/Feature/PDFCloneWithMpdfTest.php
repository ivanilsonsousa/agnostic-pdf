<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Contracts\PDFClonerDriverInterface;
use AgnosticPDF\Contracts\PDFServiceInterface;
use Tests\TestCase;

/**
 * Clona páginas de um PDF de origem usando o driver MPDF e grava o resultado.
 * Teste específico do MPDF (pula se driver ≠ mpdf).
 */
final class PDFCloneWithMpdfTest extends TestCase
{
  public function test_it_clones_pages_with_mpdf_and_saves(): void
  {
    // Este teste é específico do MPDF
    config()->set('pdf.driver', 'mpdf');

    // 1) Gera um PDF fonte temporário usando a própria lib mPDF (direto),
        //    apenas para termos um arquivo com ao menos 1 página para importar.
    $srcPath = $this->tempDir() . '/source-for-clone.pdf';
    $mpdf    = new \Mpdf\Mpdf();               // usa dependência "mpdf/mpdf"
    $mpdf->WriteHTML('<h1>PDF Fonte</h1><p>Página 1</p>');
    $mpdf->AddPage();
    $mpdf->WriteHTML('<p>Página 2</p>');
    $mpdf->Output($srcPath, 'F');

    $this->assertFileExists($srcPath, 'Deveria existir PDF fonte para clonagem.');

    // 2) Usa o driver que implementa o Cloner (no pacote ele também é um PDFServiceInterface)
    /** @var PDFClonerDriverInterface $cloner */
    $cloner = $this->app->make(PDFClonerDriverInterface::class);

    $pageCount = $cloner->prepareClone($srcPath);
    $this->assertGreaterThanOrEqual(1, $pageCount, 'PDF fonte deveria ter ao menos 1 página.');

    for ($i = 1; $i <= $pageCount; $i++) {
      $cloner->clonePage($i);
    }

    // 3) Como o driver MPDF implementa ambos os contratos, podemos salvar diretamente
    $this->assertInstanceOf(PDFServiceInterface::class, $cloner, 'Driver MPDF deveria implementar PDFServiceInterface.');

    /** @var PDFServiceInterface $serviceLike */
    $serviceLike = $cloner; // mesma instância, garantindo persistência do documento clonado
    $outPath     = $this->outputDir() . '/cloned-from-source-mpdf.pdf';
    $serviceLike->save($outPath);

    $this->assertFileExists($outPath, "Deveria ter gerado o arquivo {$outPath}");
    $this->assertGreaterThan(100, filesize($outPath), 'PDF clonado muito pequeno; clonagem pode ter falhado.');
  }
}
