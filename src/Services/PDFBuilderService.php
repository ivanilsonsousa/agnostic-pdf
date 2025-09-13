<?php

namespace AgnosticPDF\Services;

use AgnosticPDF\Contracts\PDFServiceInterface;

class PDFBuilderService
{
  protected PDFServiceInterface $pdfService;
  protected \AgnosticPDF\Services\PDFClonerService $pdfClonerService;

  /**
   * Stack de steps a serem executados.
   * Cada step é um callable sem parâmetros → usa $this internamente.
   *
   * @var array<callable>
   */
  protected array $steps = [];

  public function __construct(PDFServiceInterface $pdfService, \AgnosticPDF\Services\PDFClonerService $pdfClonerService)
  {
    $this->pdfService       = $pdfService;
    $this->pdfClonerService = $pdfClonerService;
  }

  /**
   * Adiciona uma view Blade no pipeline.
   */
  public function addView(string $view, array $data = []): self
  {
    $this->steps[] = function () use ($view, $data) {
      $this->pdfService->loadView($view, $data);
    };

    return $this;
  }

  /**
   * Adiciona um arquivo PDF no pipeline, com callback opcional por página.
   */
  public function addFile(string $pathFile, ?callable $pageCallback = null): self
  {
    $this->steps[] = function () use ($pathFile, $pageCallback) {
      $this->pdfClonerService->cloneFromFile($pathFile, $pageCallback);
    };

    return $this;
  }

  /**
   * Salva o PDF final no disco.
   */
  public function save(string $outputPath): void
  {
    $this->processSteps();

    $this->pdfService->save($outputPath);
  }

  /**
   * Exibe o PDF no navegador.
   */
  public function stream(string $filename = 'output.pdf'): void
  {
    $this->processSteps();

    $this->pdfService->stream($filename);
  }

  public function output(): string
  {
    $this->processSteps();

    return $this->pdfService->output();
  }

  /**
   * Processa todas as steps do pipeline.
   */
  protected function processSteps(): void
  {
    foreach ($this->steps as $step) {
      $step();
    }

    // Limpa as steps após processar, para reuso seguro do builder
    $this->steps = [];
  }
}
