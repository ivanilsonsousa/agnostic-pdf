<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Services\PDFService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PDFViewStreamTest extends TestCase
{
  public static function drivers(): array
  {
    return [
      ['mpdf'],
      ['dompdf'],
    ];
  }

  #[DataProvider('drivers')]
  public function test_it_renders_view_and_streams_response(string $driver): void
  {
    config()->set('pdf.driver', $driver);

    $viewsRoot = $this->tempDir() . '/views';
    $viewPath  = $viewsRoot . '/pdf/simple.blade.php';

    if (!is_dir(dirname($viewPath))) {
      mkdir(dirname($viewPath), 0777, true);
    }
    file_put_contents($viewPath, <<<BLADE
            <!doctype html>
            <html>
              <head><meta charset="utf-8"><title>PDF Test</title></head>
              <body>
                <h1>Relatório de {{ \$name }}</h1>
                <p>Este PDF foi gerado via view blade (driver: {$driver}).</p>
              </body>
            </html>
        BLADE);

    View::addLocation($viewsRoot);

    /** @var PDFService $pdf */
    $pdf = $this->app->make(PDFService::class);

    $response = $pdf->loadView('pdf.simple', ['name' => 'Integração'])
      ->streamResponse('view-stream.pdf');

    $this->assertInstanceOf(Response::class, $response);
    $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    $this->assertStringContainsString('inline; filename="view-stream.pdf"', (string) $response->headers->get('Content-Disposition'));

    $content = $response->getContent();
    $this->assertNotEmpty($content);
    $this->assertGreaterThan(100, strlen($content));
  }
}
