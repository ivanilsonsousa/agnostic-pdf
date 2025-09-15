<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Página de Imagem</title>
    <style>
        /* Sem margens para evitar estouro e quebras extras */
        @page { size: A4; margin: 0; }
        html, body { margin: 0; padding: 0; }
        /* Cada chamada desta view inicia em nova página */
        .page { page-break-before: always; }
        /* A imagem ocupa a página inteira, mantendo proporção */
        .page img {
            display: block;
            width: 100%;
            height: auto;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="page">
        <img src="{{ $image }}" alt="Imagem">
    </div>
</body>
</html>
