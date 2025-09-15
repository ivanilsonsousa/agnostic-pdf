<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Página de Texto</title>
    <style>
        @page { size: A4; margin: 20mm; }
        html, body { margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; }
        .title { font-size: 16px; font-weight: 700; margin: 0 0 6mm; }
        .content { font-size: 12px; line-height: 1.6; }
    </style>
</head>
<body>
    {{-- Uma única quebra por página gerada --}}
    <pagebreak />
    <div class="title">{{ $title }}</div>
    <div class="content">{!! nl2br(e($text)) !!}</div>
</body>
</html>
