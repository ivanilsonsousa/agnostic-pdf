<?php

namespace AgnosticPDF\Services;

use AgnosticPDF\Contracts\PDFServiceInterface;

class PDFBuilderService
{
  protected PDFServiceInterface $pdfService;

  /**
   * O serviço de clonagem, quando o driver ativo a suporta.
   *
   * É nulo com o Dompdf, que não clona páginas. Antes deste campo ser opcional,
   * `PDFManagerService::builder()` montava um `PDFClonerService` com o driver
   * qualquer que fosse: com o Dompdf ativo isso era um TypeError na construção
   * do builder — ou seja, o builder inteiro era inutilizável nesse driver,
   * mesmo para um pipeline que só renderizasse views.
   */
  protected ?PDFClonerService $pdfClonerService;

  /**
   * Stack de steps a serem executados.
   * Cada step é um callable sem parâmetros → usa $this internamente.
   *
   * @var array<callable>
   */
  protected array $steps = [];

  public function __construct(PDFServiceInterface $pdfService, ?PDFClonerService $pdfClonerService = null)
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
   * Executa um callback sobre o engine do driver, **na ordem do pipeline**.
   *
   * É o `tap()` do PDFService trazido para o builder, e existe pela mesma
   * razão: há operações que só o driver concreto sabe fazer — importar um
   * template do FPDI, registrar CSS de cabeçalho, mexer em cabeçalho e rodapé
   * de página. Sem isto, um pipeline que precisasse disso antes de clonar não
   * tinha como se expressar, e quem o escrevia acabava construindo o driver na
   * mão e abrindo mão do builder inteiro.
   *
   * @param callable $fn function(mixed $engine): void
   */
  public function tap(callable $fn): self
  {
    $this->steps[] = function () use ($fn) {
      $this->pdfService->tap($fn);
    };

    return $this;
  }

  public function addPage(array $config = []): self
  {
    $this->steps[] = function () use ($config) {
      $this->pdfService->addPage($config);
    };

    return $this;
  }

  public function eachPage(string $pathFile, callable $pageCallback, bool $force = true): self
  {
    $this->steps[] = function () use ($pathFile, $pageCallback, $force) {
      $this->cloner()->eachPageFromFile($pathFile, $pageCallback, $force);
    };

    return $this;
  }

  /**
   * Adiciona um arquivo PDF no pipeline, com callback opcional por página.
   */
  public function addFile(string $pathFile, ?callable $pageCallback = null): self
  {
    $this->steps[] = function () use ($pathFile, $pageCallback) {
      $this->cloner()->cloneFromFile($pathFile, $pageCallback);
    };

    return $this;
  }

  /**
   * Adiciona uma IMAGEM centralizada em uma nova página.
   *
   * @param array{
   *   fit?: float,
   *   margins?: float|array,
   *   orientation?: 'P'|'L'|'auto',
   *   pageFormat?: array|string|null
   * } $options
   * @param callable|null $pageCallback function(\AgnosticPDF\Drivers\MPDFDriver $driver, array $ctx): void
   */
  public function addImage(string $pathImage, array $options = [], ?callable $pageCallback = null): self
  {
    $this->steps[] = function () use ($pathImage, $options, $pageCallback) {
      $fit         = isset($options['fit']) ? max(0.0, min(1.0, (float) $options['fit'])) : 0.9;
      $orientation = $options['orientation'] ?? 'auto';
      $pageFormat  = $options['pageFormat']  ?? null;
      $margins     = $this->normalizeMargins($options['margins'] ?? null);

      [$imgWpx, $imgHpx] = @getimagesize($pathImage);

      if (!$imgWpx || !$imgHpx) {
        throw new \RuntimeException("Não foi possível obter dimensões da imagem: {$pathImage}");
      }

      if ($orientation === 'auto') {
        $orientation = ($imgWpx >= $imgHpx) ? 'L' : 'P';
      }

      // Obtemos o DRIVER para repassar ao callback (consistência com addFile)
      $driver = ($this->pdfService instanceof \AgnosticPDF\Services\PDFService)
        ? $this->pdfService->getDriver()
        : $this->pdfService;

      // Operamos no engine via tap, mas o callback receberá o DRIVER
      $this->pdfService->tap(function ($engine) use ($pathImage, $orientation, $pageFormat, $margins, $fit, $imgWpx, $imgHpx, $pageCallback, $driver) {
        // 1) cria página
        if ($pageFormat) {
          if (is_array($pageFormat) && count($pageFormat) === 2) {
            $engine->AddPageByArray([
              'orientation' => $orientation,
              'newformat'   => [$pageFormat[0], $pageFormat[1]],
            ]);
          } else {
            $engine->AddPageByArray([
              'orientation' => $orientation,
              'format'      => $pageFormat,
            ]);
          }
        } else {
          $engine->AddPage($orientation);
        }

        // 2) medidas página (mm)
        $pageWidth  = $engine->w;
        $pageHeight = $engine->h;

        // 3) área útil
        if ($margins) {
          [$mt, $mr, $mb, $ml] = $margins;
          $usableW             = $pageWidth  - ($ml + $mr);
          $usableH             = $pageHeight - ($mt + $mb);
          $originX             = $ml;
          $originY             = $mt;
        } else {
          $usableW = $pageWidth  * $fit;
          $usableH = $pageHeight * $fit;
          $originX = ($pageWidth - $usableW)  / 2;
          $originY = ($pageHeight - $usableH) / 2;
        }

        // 4) escala proporcional
        $ratioImg = $imgWpx  / $imgHpx;
        $ratioBox = $usableW / $usableH;

        if ($ratioImg >= $ratioBox) {
          $displayW = $usableW;
          $displayH = $usableW / $ratioImg;
        } else {
          $displayH = $usableH;
          $displayW = $usableH * $ratioImg;
        }

        // 5) centraliza
        $x = $originX + ($usableW - $displayW) / 2;
        $y = $originY + ($usableH - $displayH) / 2;

        // 6) desenha
        $engine->Image($pathImage, $x, $y, $displayW, $displayH, '', '', true, false);

        // 7) callback pós-render com o DRIVER (como em addFile)
        if ($pageCallback) {
          $pageCallback($driver, [
            'path'        => $pathImage,
            'x'           => $x,
            'y'           => $y,
            'width'       => $displayW,
            'height'      => $displayH,
            'pageWidth'   => $pageWidth,
            'pageHeight'  => $pageHeight,
            'usableW'     => $usableW,
            'usableH'     => $usableH,
            'orientation' => $orientation,
          ]);
        }
      });
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
   * O serviço de clonagem, ou um erro que diz o que fazer.
   *
   * A falha acontece no processamento do pipeline, não no `addFile()`, porque é
   * lá que a clonagem seria executada — e a mensagem nomeia o driver ativo, que
   * é a informação que falta a quem chamou.
   */
  private function cloner(): PDFClonerService
  {
    if ($this->pdfClonerService === null) {
      throw new \RuntimeException(sprintf(
        'A clonagem de páginas exige um driver que a suporte (MPDF); o driver ativo é "%s". Ajuste `pdf.driver` em config/pdf.php.',
        (string) config('pdf.driver', 'mpdf')
      ));
    }

    return $this->pdfClonerService;
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

  private function normalizeMargins(null|float|array $margins): ?array
  {
    if ($margins === null) {
      return null;
    }

    if (is_array($margins)) {
      $m = array_values($margins);
      $m[0] ??= 0;       // top
      $m[1] ??= $m[0];   // right
      $m[2] ??= $m[0];   // bottom
      $m[3] ??= $m[1];   // left

      return [(float) $m[0], (float) $m[1], (float) $m[2], (float) $m[3]];
    }

    $v = (float) $margins;

    return [$v, $v, $v, $v];
  }
}
