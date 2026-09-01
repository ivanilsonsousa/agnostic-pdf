<?php

declare(strict_types=1);

namespace AgnosticPDF\Services;

use AgnosticPDF\Contracts\PDFClonerDriverInterface;
use AgnosticPDF\Contracts\PDFServiceInterface;
use AgnosticPDF\Drivers\DompdfDriver;
use AgnosticPDF\Drivers\MPDFDriver;
use Illuminate\Contracts\Container\Container;

class PDFManagerService
{
  public function __construct(private Container $container)
  {
  }

  /**
   * O serviço de PDF.
   *
   * Sem argumento, resolve pelo container com a configuração de `config/pdf.php`
   * — o comportamento de sempre. Com `$config`, devolve uma instância nova cujas
   * opções são as do driver ativo com esses valores por cima.
   *
   * O `$config` existe porque **formato, orientação e margem são de cada
   * documento**, não da instalação: um relatório A4 com margem, uma etiqueta de
   * 90x29mm e um documento carimbado sem margem convivem na mesma aplicação. E
   * não dá para acertar depois — o mPDF calcula a área de escrita na construção,
   * então um `SetMargins()` posterior não reflui o conteúdo.
   *
   * @param array<string, mixed> $config opções do driver ativo a sobrepor
   */
  public function pdf(array $config = []): PDFServiceInterface
  {
    // sempre resolve conforme config atual
    if ($config === []) {
      return $this->container->make(PDFServiceInterface::class);
    }

    return new PDFService($this->driver($config));
  }

  /**
   * O serviço de clonagem, sempre com MPDF — é o único driver que a suporta.
   *
   * @param array<string, mixed> $config opções do mPDF a sobrepor
   */
  public function cloner(array $config = []): PDFClonerService
  {
    if ($config === []) {
      return $this->container->make(PDFClonerService::class);
    }

    return new PDFClonerService($this->mpdfDriver($config));
  }

  /**
   * O builder, que compartilha **a mesma instância de driver** entre
   * renderização e clonagem.
   *
   * @param array<string, mixed> $config opções do driver ativo a sobrepor
   */
  public function builder(array $config = []): PDFBuilderService
  {
    $driver = $this->driver($config);

    $cloner = $driver instanceof PDFClonerDriverInterface
      ? new PDFClonerService($driver)
      : null;

    return new PDFBuilderService(new PDFService($driver), $cloner);
  }

  /**
   * Uma instância do driver ativo, com `$config` sobre as opções dele.
   *
   * @param array<string, mixed> $config
   */
  private function driver(array $config = []): PDFServiceInterface
  {
    $driverType = config('pdf.driver', 'mpdf');

    return match ($driverType) {
      'dompdf' => new DompdfDriver([...(array) config('pdf.dompdf', []), ...$config]),
      default  => $this->mpdfDriver($config),
    };
  }

  /**
   * @param array<string, mixed> $config
   */
  private function mpdfDriver(array $config = []): MPDFDriver
  {
    return new MPDFDriver([...(array) config('pdf.mpdf', []), ...$config]);
  }
}
