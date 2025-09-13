# Agnostic PDF (Laravel)

Manipulação de PDFs para projetos **Laravel**, com **drivers intercambiáveis** (mPDF e Dompdf). Fornece uma API simples para renderizar de _views_ ou HTML, _streamar_, baixar, salvar e — quando suportado pelo driver — **clonar páginas de PDFs existentes**. Inclui ainda um serviço de **compressão** de PDFs.

> Foco: DX simples no Laravel, mantendo o código da aplicação desacoplado do driver.

---

## Sumário

- [Instalação](#instalação)
- [Configuração](#configuração)
- [Uso rápido](#uso-rápido)
- [API do Serviço de PDF](#api-do-serviço-de-pdf)
- [Clonagem de PDFs (MPDF)](#clonagem-de-pdfs-mpdf)
- [Compressão de PDFs](#compressão-de-pdfs)
- [Facade e Manager](#facade-e-manager)
- [Contratos e Drivers](#contratos-e-drivers)
- [Requisitos](#requisitos)
- [Licença](#licença)

---

## Instalação

```bash
composer require ivanilsonsousa/agnostic-pdf
```

O _Service Provider_ é descoberto automaticamente pelo Laravel (auto-discovery).

---

## Configuração

Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag=pdf-config
```

Isso criará `config/pdf.php`. Nele você define o **driver** principal e as opções específicas de cada driver.

Exemplo (conceitual):

```php
return [
    'driver' => 'mpdf', // 'mpdf' (padrão) ou 'dompdf'

    'mpdf' => [
        // opções nativas do mPDF (ex.: 'tempDir', 'format', 'orientation', 'margin_*', etc.)
    ],

    'dompdf' => [
        // opções nativas do Dompdf (ex.: 'options' => [...], 'paper', 'orientation', etc.)
    ],
];
```

> **Nota:** A clonagem de PDFs é um recurso do **MPDF**.

---

## Uso rápido

### 1) Renderizando uma _view_ para resposta HTTP (controller)

```php
use AgnosticPDF\Services\PDFService;

public function showInvoice(PDFService $pdf)
{
    $pdf->loadView('pdf.invoice', ['order' => $order]);

    // Retorne uma Response padrão do Laravel (sem 'exit'):
    return $pdf->streamResponse('invoice.pdf');
}
```

### 2) Renderizando HTML arbitrário e salvando em disco

```php
use AgnosticPDF\Services\PDFService;

public function generate(PDFService $pdf)
{
    $html = '<h1>Olá PDF</h1><p>Gerado pela aplicação.</p>';

    $pdf->loadHtml($html)->save(storage_path('app/pdfs/hello.pdf'));

    return 'ok';
}
```

### 3) Download direto

```php
use AgnosticPDF\Services\PDFService;

public function download(PDFService $pdf)
{
    $pdf->loadView('pdf.report')->download('relatorio.pdf');
    // Baixa o arquivo no navegador do usuário.
}
```

> Dica: para pipelines HTTP no Laravel, prefira `streamResponse()` (retorna `Illuminate\Http\Response`).

---

## API do Serviço de PDF

A interface comum aos drivers é `AgnosticPDF\Contracts\PDFServiceInterface`. Os métodos expostos pelo **serviço principal** (`AgnosticPDF\Services\PDFService`) espelham essa interface:

- `loadHtml(string $html): self`
  Carrega HTML (da página em memória) para ser renderizado pelo driver.

- `loadView(string $view, array $data = []): self`
  Renderiza uma _view_ do Laravel e carrega o HTML resultante.

- `output(): string`
  Retorna o binário do PDF renderizado como _string_.

- `download(string $filename): void`
  Força o _download_ no navegador.

- `save(string $path): void`
  Salva o PDF no caminho indicado.

- `stream(string $filename): void`
  Envia o PDF e finaliza a resposta.

  > Para integração limpa com Laravel, prefira `streamResponse()`.

- `streamResponse(string $filename): \Illuminate\Http\Response`
  Retorna uma `Response` com o PDF em _inline_.

---

## Clonagem de PDFs (MPDF)

A clonagem (importar páginas de um PDF existente para o documento atual) é implementada pelo contrato `AgnosticPDF\Contracts\PDFClonerDriverInterface` e está disponível com o **driver MPDF**.

### Serviço de clonagem

```php
use AgnosticPDF\Services\PDFClonerService;

public function cloneAll(PDFClonerService $cloner /* driver: MPDF */)
{
    // Clona todas as páginas do arquivo de origem
    $cloner->cloneFromFile(storage_path('app/input.pdf'));

    // A partir daqui, as páginas clonadas estão no documento do driver em uso.
    // Para emitir o PDF, utilize o fluxo da sua aplicação (ver seção "Facade e Manager").
}
```

Assinatura (resumo) do método principal:

```php
cloneFromFile(string $file, ?callable $callback = null, bool $force = true): self
```

- `$callback` (opcional): será chamado a cada página clonada como `fn(PDFClonerService $svc, int $pageNo, int $pageCount)`.
- `$force` (opcional): comportamento de fluxo conforme sua aplicação.

> **Importante:** Para orquestrar **clonagem + renderização** no **mesmo documento**, utilize o **Manager** (abaixo), que garante que clonagem e emissão compartilham a mesma instância de driver MPDF.

---

## Compressão de PDFs

Há um serviço de compressão baseado em _processo externo_ (executado via `Symfony\Component\Process\Process`):

```php
use AgnosticPDF\Services\PDFCompressor;

public function compress(PDFCompressor $compressor)
{
    $compressedPath = $compressor->reduce(storage_path('app/pdfs/original.pdf'));
    // $compressedPath aponta para o arquivo comprimido (normalmente em diretório temporário)
}
```

Erros de compressão lançam `AgnosticPDF\Exceptions\PDFCompressException`, que expõe `getContext(): array` com detalhes úteis de depuração (comando, saída, caminho de entrada, etc.).

---

## Facade e Manager

A _facade_ `AgnosticPDF\Facades\PDF` resolve o **Manager** (`AgnosticPDF\Services\PDFManagerService`), que agrega:

- o serviço de PDF (renderização),
- o serviço de clonagem (quando disponível),
- o compressor.

O Manager oferece um **builder** para cenários em que você quer **encadear** operações (ex.: clonar páginas e em seguida renderizar/salvar) compartilhando a **mesma instância de driver**:

```php
use AgnosticPDF\Facades\PDF;

$builder = PDF::builder();

// Exemplo ilustrativo de pipeline (os métodos encadeáveis são do seu builder):
// $builder
//     ->cloneFromFile(storage_path('app/input.pdf'))
//     ->loadView('pdf.cover', ['title' => 'Meu PDF'])
//     ->streamResponse('final.pdf');
```

> O **builder** é útil principalmente para **clonagem com MPDF** seguida de emissão do PDF, assegurando que tudo ocorra no mesmo documento interno.

---

## Contratos e Drivers

### Contratos

- `AgnosticPDF\Contracts\PDFServiceInterface`
  Operações de renderização/saída: `loadHtml`, `loadView`, `output`, `download`, `save`, `stream`, `streamResponse`.

- `AgnosticPDF\Contracts\PDFClonerDriverInterface` (**MPDF**)
  Clonagem de páginas:
  - `prepareClone(string $pathFile): int` → retorna o número de páginas do PDF origem;
  - `clonePage(int $pageNo): void` → importa a página para o documento atual.

### Drivers disponíveis

- `AgnosticPDF\Drivers\MPDFDriver`
  Implementa `PDFServiceInterface` **e** `PDFClonerDriverInterface`.
  Fornece `getMpdf(): \Mpdf\Mpdf` para configurações avançadas do mPDF.

- `AgnosticPDF\Drivers\DompdfDriver`
  Implementa `PDFServiceInterface`.
  Por padrão, habilita recursos remotos e usa `A4 portrait`.

> Você seleciona o driver ativo via `config('pdf.driver')`.

---

## Requisitos

- Laravel `^12.0` (auto-discovery de provider já configurado)
- Drivers:
  - `mpdf/mpdf:^8.2`
  - `dompdf/dompdf:^3.1`

- PHP: utilize a versão suportada pelo seu Laravel/driver.

---

### Namespace & Provider

As classes públicas estão sob `AgnosticPDF\...` e o _Service Provider_ é `AgnosticPDF\PDFServiceProvider` (auto-discovery via `composer.json`).

---

## Exemplos rápidos (copie-e-cole)

**Controller – stream inline**

```php
use AgnosticPDF\Services\PDFService;

public function show(PDFService $pdf)
{
    return $pdf->loadView('pdf.ticket', ['ticket' => $ticket])
               ->streamResponse('ticket.pdf');
}
```

**Salvar em disco**

```php
use AgnosticPDF\Services\PDFService;

$pdf->loadHtml('<h1>Relatório</h1>')->save(storage_path('app/pdfs/relatorio.pdf'));
```

**Compressão**

```php
use AgnosticPDF\Services\PDFCompressor;

$compressed = app(PDFCompressor::class)->reduce(storage_path('app/pdfs/relatorio.pdf'));
```

**Clonagem (MPDF) + emissão via Builder**

```php
use AgnosticPDF\Facades\PDF;

PDF::builder()
   ->cloneFromFile(storage_path('app/pdfs/base.pdf'))
   ->loadView('pdf.appendix', ['data' => $data])
   ->streamResponse('final.pdf');
```
