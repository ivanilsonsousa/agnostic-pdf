<?php

namespace AgnosticPDF\Drivers;

use AgnosticPDF\Contracts\PDFClonerDriverInterface;
use AgnosticPDF\Contracts\PDFServiceInterface;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;

class MPDFDriver implements PDFServiceInterface, PDFClonerDriverInterface
{
  protected Mpdf $mpdf;

  public function __construct(array $config = [])
  {
    $this->mpdf = new Mpdf($config);
  }

  public function loadHtml(string $html): self
  {
    $this->mpdf->WriteHTML($html);

    return $this;
  }

  public function loadView(string $view, array $data = []): self
  {
    $html = View::make($view, $data)->render();

    return $this->loadHtml($html);
  }

  public function output(): string
  {
    return $this->mpdf->Output('', 'S');
  }

  public function download(string $filename): void
  {
    $this->mpdf->Output($filename, 'D');
  }

  public function save(string $path): void
  {
    $this->mpdf->Output($path, 'F');
  }

  public function stream(string $filename): void
  {
    $this->streamResponse($filename)->send();

    exit;
  }

  public function streamResponse(string $filename): \Illuminate\Http\Response
  {
    $pdfContent = $this->mpdf->Output('', 'S');

    return Response::make($pdfContent, 200, [
      'Content-Type'        => 'application/pdf',
      'Content-Disposition' => 'inline; filename="' . $filename . '"',
      'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
      'Pragma'              => 'no-cache',
      'Expires'             => '0',
    ]);
  }

  public function prepareClone(string $pathFile): int
  {
    return $this->mpdf->SetSourceFile($pathFile);
  }

  public function clonePage(int $pageNo): void
  {
    $tplId       = $this->mpdf->importPage($pageNo);
    $size        = $this->mpdf->getTemplateSize($tplId);
    $orientation = $size['width'] < $size['height'] ? 'P' : 'L';
    $newformat   = [max($size['width'], $size['height']), min($size['width'], $size['height'])];

    $this->mpdf->AddPageByArray([
      'orientation' => $orientation,
      'newformat'   => $newformat,
    ]);
    $this->mpdf->useTemplate($tplId);
  }

  /**
   * Getter para acessar a instância do MPDF diretamente
   * Permite configurações avançadas quando necessário
   */
  public function getMpdf(): Mpdf
  {
    return $this->mpdf;
  }

  public function writeCss(string $css): self
  {
    $this->mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);

    return $this;
  }

  public function writeCssFile(string $path): self
  {
    $css = file_get_contents($path);

    return $this->writeCss($css);
  }

  public function writeHtml(string $html): self
  {
    $this->mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

    return $this;
  }
}
