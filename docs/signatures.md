# Assinaturas digitais

O módulo de assinatura é uma API PHP independente do renderizador e do
Laravel. `PdfSignerInterface` recebe e devolve bytes; a implementação atual,
`PapierPdfSigner`, usa o parser e o escritor incremental do Papier e o OpenSSL
do PHP para produzir CMS/PKCS#7.

## O que é preservado

Cada assinatura acrescenta uma revisão ao fim do arquivo, com `/Prev` apontando
para a revisão anterior. A revisão contém:

- um campo e widget de assinatura;
- a aparência visual opcional;
- um dicionário `/Sig` com `ByteRange`;
- um CMS destacado em `/Contents`.

O PDF anterior permanece, byte por byte, como prefixo do resultado. Não passe o
arquivo assinado por mPDF, Dompdf, FPDI, conversores ou compressores depois
disso: qualquer regravação pode invalidar as assinaturas existentes.

## Assinatura básica com P12/PFX

```php
use AgnosticPDF\Drivers\PapierPdfSigner;
use AgnosticPDF\Signatures\Certificate;
use AgnosticPDF\Signatures\SignatureOptions;

$certificate = Certificate::fromPkcs12File(
    '/run/secrets/signing-certificate.p12',
    $_ENV['PDF_CERTIFICATE_PASSWORD'],
);

$signer = new PapierPdfSigner();
$signed = $signer->sign(
    file_get_contents('/documents/input.pdf'),
    $certificate,
    options: SignatureOptions::make()
        ->signer('Maria da Silva')
        ->reason('Aprovação')
        ->location('Fortaleza'),
);
```

`Certificate::fromPkcs12()` recebe os bytes do P12/PFX. Uma senha incorreta,
arquivo inválido ou chave que não corresponda ao certificado gera
`CertificateException`.

## PEM

```php
$certificate = Certificate::fromPem(
    certificate: '/run/secrets/certificate.pem',
    privateKey: '/run/secrets/private-key.pem',
    password: $_ENV['PRIVATE_KEY_PASSWORD'] ?? '',
    chainPem: [$intermediateCertificatePem],
);
```

Certificado, chave e cadeia podem ser fornecidos como bytes PEM ou caminhos
locais. A chave é normalizada em memória e a correspondência com o certificado
é conferida antes da assinatura.

## Geração e certificado autoassinado

```php
$certificate = Certificate::create()
    ->commonName('Sistema de Documentos')
    ->organization('Minha Organização')
    ->organizationalUnit('Assinatura de documentos')
    ->country('BR')
    ->email('documentos@example.org')
    ->validFor(1825)
    ->keyBits(3072)
    ->digestAlgorithm('sha256')
    ->generate();

$p12 = $certificate->exportPkcs12($_ENV['PDF_CERTIFICATE_PASSWORD']);
```

O certificado gerado é RSA, autoassinado, `CA:FALSE` e restrito a assinatura
digital. A biblioteca gera/manipula os bytes, mas deliberadamente não escolhe
onde armazenar certificado, senha ou chave privada.

`CertificateBuilder` aceita um `CertificateIssuerInterface`. Isso deixa a API
pronta para um emissor de CA interna sem acoplar assinatura de PDF à emissão:

```php
$certificate = Certificate::create($internalCertificateIssuer)
    ->commonName('Assinador interno')
    ->generate();
```

Um certificado autoassinado não tem confiança pública automática em Acrobat,
VALIDAR/ITI ou outros leitores. Distribuir a âncora pública a quem verifica é
responsabilidade da instalação. Autoassinado também não significa, por si só,
ICP-Brasil, assinatura avançada ou qualificada.

## Aparência visual

As coordenadas estão em pontos de PDF, com origem no canto inferior esquerdo.

```php
use AgnosticPDF\Signatures\SignatureAppearance;

$appearance = SignatureAppearance::make()
    ->page(1) // use -1 para a última página
    ->position(36, 24)
    ->size(280, 71)
    ->pdf('/assets/complete-seal.pdf');

$signed = $signer->sign($pdf, $certificate, $appearance);
```

`pdf()` aceita caminho ou bytes e usa a primeira página como Form XObject. Isso
mantém vetores, fontes e texto selecionável sem reescrever o documento assinado.
`image()` continua disponível para PNG/JPEG completo. É possível combinar uma
imagem com texto ou construir a caixa textual padrão:

```php
$appearance = SignatureAppearance::default(
    signerName: 'Maria da Silva',
    reason: 'Aprovação',
    location: 'Fortaleza',
)->page(1)->position(36, 24)->size(220, 70);
```

Também estão disponíveis `text()`, `signer()`, `date()`, `reason()` e
`location()`. Passe `null` como aparência para uma assinatura invisível. A
aparência sempre faz parte da mesma revisão incremental do CMS.

Quando `SignatureOptions::signer()` é informado, o nome também é gravado em
`/Name` no dicionário assinado e nos metadados do widget (`/T`, `/TU` e
`/Contents`); `/M` recebe a data. Isso melhora a identificação em leitores que
tratam a assinatura pelo painel de anotações.

## Múltiplas assinaturas

Assine novamente os bytes devolvidos pela operação anterior:

```php
$revisionA = $signer->sign($original, $certificateA);
$revisionB = $signer->sign($revisionA, $certificateB);
$revisionC = $signer->sign($revisionB, $certificateC);
```

O driver preserva os campos do AcroForm, anotações e revisões já existentes.
Isso também vale para uma assinatura anterior produzida por outro assinador,
desde que o PDF seja legível e sua política permita uma nova assinatura.

Uma certificação DocMDP com permissão `P=1` proíbe alterações posteriores e é
recusada antes de assinar. `P=2` e `P=3` permitem o fluxo de aprovação atual.
Políticas FieldMDP específicas ainda não são interpretadas.

## Verificação e confiança

```php
use AgnosticPDF\Signatures\TrustStore;

$trustStore = TrustStore::fromFile('/etc/my-app/trusted-signers.pem');
$verification = $signer->verify($signedPdf, $trustStore);

echo $verification->count();

foreach ($verification->signatures as $signature) {
    $signature->signerName;
    $signature->reason;
    $signature->location;
    $signature->cryptographicallyValid;
    $signature->documentIntegrityValid;
    $signature->certificateTrusted;
    $signature->certificateValidAtSigningTime;
    $signature->algorithm;
    $signature->coversRevision;
    $signature->revisionLength;
    $signature->hasLaterChanges;

    $signature->certificate?->subject;
    $signature->certificate?->issuer;
    $signature->certificate?->serialNumber;
    $signature->certificate?->validFrom;
    $signature->certificate?->validTo;
}
```

São respostas diferentes:

- `cryptographicallyValid`: o CMS confere para os intervalos assinados;
- `documentIntegrityValid`: os bytes cobertos não foram adulterados;
- `certificateTrusted`: a cadeia termina no `TrustStore` informado;
- `hasLaterChanges`: existem bytes de uma revisão posterior àquela assinatura.

Sem trust store, a integridade é validada e `certificateTrusted` fica `null`.
Por isso uma assinatura autoassinada pode ser criptograficamente válida e não
ter confiança estabelecida. `TrustStore::fromPem()` aceita um ou mais
certificados PEM concatenados.

Um arquivo PDF válido sem campos assinados retorna `VerificationResult` vazio.
PDF inválido/corrompido gera `PdfSignatureException`.

## Laravel

O provider registra `PdfSignerInterface` separadamente dos drivers de
renderização. Injete o contrato diretamente ou use o manager:

```php
use AgnosticPDF\Contracts\PdfSignerInterface;
use AgnosticPDF\Facades\PDF;

$signer = app(PdfSignerInterface::class);
$sameSigner = PDF::signer();
```

O núcleo de certificados, assinatura e verificação não chama helpers do Laravel
e pode ser usado diretamente em qualquer aplicação Composer.

## Segurança operacional

- mantenha P12/PFX e senhas fora do repositório e do diretório público;
- restrinja permissões de leitura ao processo que assina;
- não registre senhas, chaves privadas, P12 ou conteúdo sensível;
- faça backup seguro da chave e defina uma política explícita de rotação;
- trate o certificado público autoassinado como âncora e distribua-o por um
  canal confiável;
- preserve os PDFs assinados como imutáveis e acrescente apenas revisões
  incrementais compatíveis.

Os arquivos temporários inevitáveis para o OpenSSL recebem nomes imprevisíveis,
modo `0600` e são removidos em `finally`, inclusive em exceções.

## Limitações atuais

- CMS de aprovação `adbe.pkcs7.detached`, sem alegação de conformidade PAdES;
- sem TSA, timestamp confiável, OCSP, CRL, DSS ou LTV;
- sem integração específica com GOV.BR, ICP-Brasil, HSM ou token A3;
- DocMDP `P=1` é detectado, mas FieldMDP ainda não;
- a verificação detecta revisão posterior, sem classificá-la semanticamente;
- PDFs criptografados ou recursos PDF que o parser não suporte são rejeitados.

Para PAdES-B-T/LT/LTA será necessário adicionar timestamp RFC 3161 e, nos
níveis de longo prazo, evidências de revogação e DSS sem alterar a API pública.
