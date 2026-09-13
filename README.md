# Agnostic PDF

Manipulação de PDFs com **drivers intercambiáveis** (mPDF e Dompdf), assinatura
digital incremental e integração opcional com Laravel. Fornece uma API para
renderizar, clonar, comprimir, assinar e verificar PDFs.

> Foco: DX simples no Laravel, mantendo o código da aplicação desacoplado do driver.

---

## Sumário

- [Instalação](#instalação)
- [Configuração](#configuração)
  - [Configuração por chamada](#configuração-por-chamada)
- [Uso rápido](#uso-rápido)
- [Assinatura digital](#assinatura-digital)
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

### Configuração por chamada

`config/pdf.php` é a configuração **da instalação**. Só que formato, orientação e
margem costumam ser de **cada documento**: um relatório A4 com margem, uma
etiqueta de 90x29mm e um documento carimbado sem margem convivem na mesma
aplicação.

Para isso, `pdf()`, `builder()` e `cloner()` aceitam um array que é aplicado
**por cima** das opções do driver ativo:

```php
use AgnosticPDF\Facades\PDF;

// Um relatório A4 com margens próprias, sem tocar no config global:
return PDF::pdf([
        'format'       => 'A4',
        'margin_left'  => 16,
        'margin_right' => 16,
        'margin_top'   => 26,
    ])
    ->loadView('pdf.report', ['data' => $data])
    ->streamResponse('relatorio.pdf');

// Uma etiqueta, na mesma aplicação e na mesma requisição:
$etiqueta = PDF::pdf(['format' => [90, 29], 'margin_top' => 2])->loadView('pdf.label');
```

Só as chaves informadas mudam; as demais continuam vindo de `config/pdf.php`.
Cada chamada devolve uma instância nova, então uma não interfere na outra.

> **Por que não ajustar depois de construir?** Porque não funciona: o mPDF
> calcula a área de escrita na construção. Um `SetMargins()` posterior não
> reflui o conteúdo — a página continua com a largura útil antiga e o texto
> sangra até a borda, sem erro nenhum. Daí a configuração precisar chegar no
> momento em que o driver é criado.

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

## Assinatura digital

O assinador é independente do mPDF e do Dompdf. Ele acrescenta uma assinatura
CMS/PKCS#7 destacada (`adbe.pkcs7.detached`) em uma **revisão incremental**: os
bytes que já existiam permanecem intactos. Assim, uma segunda assinatura não
reescreve nem invalida a primeira.

Consulte [docs/signatures.md](docs/signatures.md) para a API completa, modelo de
confiança, segurança, múltiplas assinaturas e limitações.

```php
use AgnosticPDF\Drivers\PapierPdfSigner;
use AgnosticPDF\Signatures\Certificate;
use AgnosticPDF\Signatures\SignatureAppearance;
use AgnosticPDF\Signatures\SignatureOptions;

$certificate = Certificate::fromPkcs12File(
    '/run/secrets/signing-certificate.p12',
    $_ENV['PDF_CERTIFICATE_PASSWORD'],
);

$appearance = SignatureAppearance::make()
    ->page(1)
    ->position(36, 24) // pontos, origem inferior esquerda
    ->size(280, 71)
    ->pdf('/path/to/vector-seal.pdf');

$signed = (new PapierPdfSigner())->sign(
    file_get_contents('/path/to/input.pdf'),
    $certificate,
    $appearance,
    SignatureOptions::make()
        ->signer('Maria da Silva')
        ->reason('Aprovação do documento'),
);

file_put_contents('/path/to/signed.pdf', $signed);
```

`pdf()` usa a primeira página de um PDF como aparência vetorial, preservando o
texto. `image()` aceita bytes ou caminho local para PNG/JPEG. Sem aparência, a
assinatura é invisível. Também é possível usar
`SignatureAppearance::default(...)` para uma caixa de texto simples.

### Certificado autoassinado

A biblioteca pode gerar um certificado RSA autoassinado para instalações que
controlam sua própria âncora de confiança:

```php
$certificate = Certificate::create()
    ->commonName('Sistema de Documentos')
    ->organization('Minha Organização')
    ->organizationalUnit('Assinatura de documentos')
    ->country('BR')
    ->validFor(1825)
    ->generate();

$p12 = $certificate->exportPkcs12($_ENV['PDF_CERTIFICATE_PASSWORD']);
```

A aplicação, não a biblioteca, deve persistir e proteger a chave privada. Um
certificado autoassinado prova a integridade criptográfica, mas aparece como
**não confiável** até que seu certificado público seja instalado ou fornecido
como âncora de confiança. Ele não equivale por si só a um certificado emitido
por uma autoridade certificadora ou a uma assinatura qualificada.

### Verificação

```php
use AgnosticPDF\Signatures\TrustStore;

$trust = TrustStore::fromFile('/path/to/trusted-certificates.pem');
$result = (new PapierPdfSigner())->verify($signed, $trust);

foreach ($result->signatures as $signature) {
    $signature->signerName;
    $signature->cryptographicallyValid;
    $signature->certificateTrusted;
    $signature->certificateValidAtSigningTime;
    $signature->algorithm;
    $signature->hasLaterChanges;
    $signature->certificate?->subjectName();
}
```

Sem `TrustStore`, a integridade continua sendo verificada e
`certificateTrusted` fica `null`. PDFs sem assinatura retornam um resultado
vazio. PDFs corrompidos e documentos certificados com DocMDP `P=1` são
rejeitados com `PdfSignatureException`.

No Laravel, injete `AgnosticPDF\Contracts\PdfSignerInterface` ou use
`PDF::signer()`. Fora dele, instancie `PapierPdfSigner` diretamente.

### Limites atuais

- assinatura CMS de aprovação compatível com ISO 32000, sem carimbo de tempo
  TSA, OCSP/CRL ou perfil PAdES-LT/LTA;
- suporte a restrição DocMDP `P=1`; políticas FieldMDP não são interpretadas;
- a verificação informa alterações posteriores, mas não classifica cada revisão
  posterior como permitida ou maliciosa.

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

A _facade_ `AgnosticPDF\Facades\PDF` resolve o **Manager** (`AgnosticPDF\Services\PDFManagerService`), cujos três métodos — `pdf()`, `builder()` e `cloner()` — aceitam [configuração por chamada](#configuração-por-chamada). Ele agrega:

- o serviço de PDF (renderização),
- o serviço de clonagem (quando disponível),
- o compressor.

O Manager oferece um **builder** para cenários em que você quer **encadear** operações (ex.: clonar páginas e em seguida renderizar/salvar) compartilhando a **mesma instância de driver**:

```php
use AgnosticPDF\Facades\PDF;

PDF::builder()
   ->addView('pdf.cover', ['title' => 'Meu PDF'])   // uma capa renderizada
   ->addFile(storage_path('app/input.pdf'))         // e o PDF existente em seguida
   ->save(storage_path('app/pdfs/final.pdf'));
```

Métodos do builder: `addView`, `addPage`, `addFile`, `addImage`, `eachPage` e, para emitir, `save`, `stream` e `output`.

> O **builder** é útil principalmente para **clonagem com MPDF** seguida de emissão do PDF, assegurando que tudo ocorra no mesmo documento interno. Com o Dompdf ele funciona para pipelines que não clonam; `addFile`/`eachPage` lançam `RuntimeException` explicando que a clonagem exige MPDF.

---

## Contratos e Drivers

### Contratos

- `AgnosticPDF\Contracts\PDFServiceInterface`
  Operações de renderização/saída: `loadHtml`, `loadView`, `output`, `download`, `save`, `stream`, `streamResponse`, `addPage`, `getEngine` e `tap`.

- `AgnosticPDF\Contracts\PDFClonerDriverInterface` (**MPDF**)
  Clonagem de páginas:
  - `prepareClone(string $pathFile): int` → retorna o número de páginas do PDF origem;
  - `clonePage(int $pageNo): void` → importa a página para o documento atual.

- `AgnosticPDF\Contracts\PdfSignerInterface`
  Assinatura incremental e verificação de assinaturas existentes, sem depender
  do driver de renderização ou do Laravel.

### Drivers disponíveis

- `AgnosticPDF\Drivers\MPDFDriver`
  Implementa `PDFServiceInterface` **e** `PDFClonerDriverInterface`.
  Fornece `getEngine(): \Mpdf\Mpdf` para configurações avançadas do mPDF — ou `tap(callable)`, que entrega a mesma instância a um callback.

- `AgnosticPDF\Drivers\DompdfDriver`
  Implementa `PDFServiceInterface`.
  Por padrão, habilita recursos remotos e usa `A4 portrait`.

> Você seleciona o driver ativo via `config('pdf.driver')`.

---

## Requisitos

- PHP `^8.2` (Laravel 13 requer PHP 8.3 ou superior)
- extensões `openssl`, `mbstring` e `zlib`
- Laravel `^12.0 || ^13.0` somente para provider, facade, views e respostas HTTP
- Drivers:
  - `mpdf/mpdf:^8.2`
  - `dompdf/dompdf:^3.1`
  - `papier/papier:^3.0`

---

### Namespace & Provider

As classes públicas estão sob `AgnosticPDF\...` e o _Service Provider_ é `AgnosticPDF\AgnosticPDFServiceProvider` (auto-discovery via `composer.json`).

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
   ->addFile(storage_path('app/pdfs/base.pdf'))
   ->addView('pdf.appendix', ['data' => $data])
   ->save(storage_path('app/pdfs/final.pdf'));
```
