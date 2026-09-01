<?php

declare(strict_types=1);

namespace Tests\Feature;

use AgnosticPDF\Facades\PDF;
use Mpdf\Mpdf;
use Tests\TestCase;

/**
 * `tap()` no builder: o engine, na ordem do pipeline.
 *
 * Sem ele, um pipeline que precisa preparar o engine **antes** de clonar não
 * tem como se expressar — o caso concreto é importar templates do FPDI, que
 * precisa acontecer antes de a clonagem trocar o arquivo de origem.
 */
final class PDFBuilderTapTest extends TestCase
{
    public function test_it_runs_the_callback_on_the_engine(): void
    {
        config()->set('pdf.driver', 'mpdf');

        $seen = null;

        PDF::builder()
            ->tap(function (Mpdf $engine) use (&$seen): void {
                $engine->SetTitle('Documento carimbado');
                $seen = $engine;
            })
            ->addPage()
            ->output();

        $this->assertInstanceOf(Mpdf::class, $seen);
    }

    public function test_it_runs_in_pipeline_order(): void
    {
        config()->set('pdf.driver', 'mpdf');

        $order = [];

        PDF::builder()
            ->tap(function () use (&$order): void {
                $order[] = 'antes';
            })
            ->addPage()
            ->tap(function () use (&$order): void {
                $order[] = 'depois';
            })
            ->output();

        $this->assertSame(['antes', 'depois'], $order);
    }

    /**
     * O engine do `tap` e o driver que a clonagem entrega são a **mesma**
     * instância — é o que permite importar um template antes e usá-lo durante.
     */
    public function test_the_engine_is_shared_with_the_cloner(): void
    {
        config()->set('pdf.driver', 'mpdf');

        $fromTap = null;
        $fromClone = null;

        PDF::builder()
            ->tap(function (Mpdf $engine) use (&$fromTap): void {
                $fromTap = $engine;
            })
            ->addFile(__DIR__ . '/../Examples/sample-1.pdf', function ($driver) use (&$fromClone): void {
                $fromClone ??= $driver->getEngine();
            })
            ->output();

        $this->assertSame($fromTap, $fromClone);
    }
}
