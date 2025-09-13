<?php

namespace AgnosticPDF\Drivers;

use AgnosticPDF\Contracts\PDFServiceInterface;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;

class DompdfDriver implements PDFServiceInterface
{
  protected Dompdf $dompdf;

  public function __construct(array $config = [])
  {
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $this->dompdf = new \Dompdf\Dompdf($options);
    $this->dompdf->setPaper('A4', 'portrait');
  }

  public function loadHtml(string $html): self
  {
    $this->dompdf->loadHtml($html);

    return $this;
  }

  public function loadView(string $view, array $data = []): self
  {
    $html = View::make($view, $data)->render();

    return $this->loadHtml($html);
  }

  public function output(): string
  {
    $this->dompdf->render();

    return $this->dompdf->output();
  }

  public function download(string $filename): void
  {
    $this->dompdf->render();
    $this->dompdf->stream($filename, ['Attachment' => true]);
  }

  public function save(string $path): void
  {
    file_put_contents($path, $this->output());
  }

  public function stream(string $filename): void
  {
    $this->streamResponse($filename)->send();

    exit;
  }

  public function streamResponse(string $filename): \Illuminate\Http\Response
  {
    $this->dompdf->render();

    return Response::make($this->dompdf->output(), 200, [
      'Content-Type'        => 'application/pdf',
      'Content-Disposition' => 'inline; filename="' . $filename . '"',
      'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
      'Pragma'              => 'no-cache',
      'Expires'             => '0',
    ]);
  }
}
