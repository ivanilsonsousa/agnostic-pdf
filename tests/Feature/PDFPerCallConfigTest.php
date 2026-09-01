<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Facades\PDF;
use AgnosticPDF\Services\PDFBuilderService;
use AgnosticPDF\Services\PDFClonerService;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Configuração por chamada.
 *
 * Formato, orientação e margem são de cada documento, não da instalação: um
 * relatório A4 com margem, uma etiqueta pequena e um documento carimbado sem
 * margem convivem na mesma aplicação. Sem isto, só a configuração global de
 * `config/pdf.php` chega ao driver, e não há como corrigir depois — o mPDF
 * calcula a área de escrita na construção.
 */
final class PDFPerCallConfigTest extends TestCase
{
  public function test_pdf_without_config_still_uses_the_global_configuration(): void
  {
    config()->set('pdf.driver', 'mpdf');
    config()->set('pdf.mpdf', ['format' => 'A4', 'margin_left' => 5]);

    $mpdf = PDF::pdf()->getEngine();

    $this->assertSame(5.0, round($mpdf->lMargin, 4));
  }

  public function test_pdf_config_overrides_only_the_keys_it_names(): void
  {
    config()->set('pdf.driver', 'mpdf');
    config()->set('pdf.mpdf', ['format' => 'A4', 'margin_left' => 5, 'margin_top' => 5]);

    $mpdf = PDF::pdf(['margin_left' => 16])->getEngine();

    $this->assertSame(16.0, round($mpdf->lMargin, 4), 'a chave passada vence');
    $this->assertSame(5.0, round($mpdf->tMargin, 4), 'as demais continuam vindo do config');
  }

  /**
   * O que motivou a configuração por chamada: com a margem só no construtor, a
   * área de escrita fecha certo. É o que um `SetMargins()` posterior não faz.
   */
  public function test_page_width_reflects_the_margins_given_per_call(): void
  {
    config()->set('pdf.driver', 'mpdf');
    config()->set('pdf.mpdf', ['format' => 'A4', 'margin_left' => 0, 'margin_right' => 0]);

    $mpdf = PDF::pdf(['margin_left' => 16, 'margin_right' => 16])->getEngine();

    // A4 tem 210mm de largura.
    $this->assertEqualsWithDelta(178.0, $mpdf->pgwidth, 0.01);
  }

  public function test_each_call_gets_its_own_instance(): void
  {
    config()->set('pdf.driver', 'mpdf');
    config()->set('pdf.mpdf', ['format' => 'A4']);

    $small = PDF::pdf(['margin_left' => 2])->getEngine();
    $large = PDF::pdf(['margin_left' => 30])->getEngine();

    $this->assertSame(2.0, round($small->lMargin, 4));
    $this->assertSame(30.0, round($large->lMargin, 4), 'uma chamada não contamina a outra');
  }

  public function test_dompdf_also_accepts_per_call_config(): void
  {
    config()->set('pdf.driver', 'dompdf');
    config()->set('pdf.dompdf', ['paper' => 'A4', 'orientation' => 'portrait']);

    $pdf = PDF::pdf(['orientation' => 'landscape']);

    $pdf->loadHtml('<h1>Paisagem</h1>');

    $this->assertStringStartsWith('%PDF-', $pdf->output());
  }

  #[DataProvider('drivers')]
  public function test_builder_accepts_per_call_config(string $driver): void
  {
    config()->set('pdf.driver', $driver);

    $builder = PDF::builder(['orientation' => 'L']);

    $this->assertInstanceOf(PDFBuilderService::class, $builder);
  }

  public function test_cloner_accepts_per_call_config(): void
  {
    config()->set('pdf.driver', 'mpdf');
    config()->set('pdf.mpdf', ['format' => 'A4']);

    $this->assertInstanceOf(PDFClonerService::class, PDF::cloner(['format' => 'A5']));
  }

  /**
   * Antes, `builder()` com Dompdf montava um `PDFClonerService` com o driver
   * qualquer que fosse — TypeError na construção, e o builder ficava
   * inutilizável nesse driver até para um pipeline que só renderiza views.
   */
  public function test_builder_works_with_dompdf_for_pipelines_that_do_not_clone(): void
  {
    config()->set('pdf.driver', 'dompdf');

    $pdf = PDF::builder()->addPage()->output();

    $this->assertStringStartsWith('%PDF-', $pdf);
  }

  public function test_cloning_with_dompdf_fails_saying_why(): void
  {
    config()->set('pdf.driver', 'dompdf');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('exige um driver que a suporte (MPDF)');

    PDF::builder()->addFile(__DIR__ . '/../Examples/sample-1.pdf')->output();
  }

  public static function drivers(): array
  {
    return [['mpdf'], ['dompdf']];
  }
}
