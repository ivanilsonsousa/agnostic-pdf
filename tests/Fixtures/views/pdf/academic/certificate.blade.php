<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Certificado de Conclusão</title>
    <style>
        @page { margin: 30mm 20mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; }
        .header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .logo { width: 64px; height: 64px; }
        .inst { font-size: 18px; font-weight: 700; letter-spacing: .4px; }
        .doc-title { margin: 14px 0 24px; font-size: 26px; text-align: center; letter-spacing: .5px; }
        .box { border: 1px solid #bbb; padding: 18px; border-radius: 6px; }
        .strong { font-weight: 700; }
        .meta { margin-top: 18px; font-size: 12px; color: #555; }
        .signatures { margin-top: 40px; display: flex; justify-content: space-between; gap: 30px; }
        .sig { width: 45%; text-align: center; }
        .sig .line { margin-top: 50px; border-top: 1px solid #333; }
        .footer { position: fixed; bottom: 15mm; left: 20mm; right: 20mm; font-size: 10px; color: #666; text-align:center; }
    </style>
</head>
<body>
    <div class="header">
        <svg class="logo" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="46" fill="#2166E3" />
            <text x="50" y="58" font-size="36" text-anchor="middle" fill="#fff" font-family="DejaVu Sans, Arial">A</text>
        </svg>
        <div class="inst">{{ $instituicao }}</div>
    </div>

    <div class="doc-title">CERTIFICADO DE CONCLUSÃO</div>

    <div class="box">
        Certificamos que <span class="strong">{{ $aluno }}</span>, portador(a) do CPF
        <span class="strong">{{ $cpf }}</span>, concluiu o curso de
        <span class="strong">{{ $curso }}</span>, obtendo o título de
        <span class="strong">{{ $titulo }}</span>.
        <div class="meta">
            Emitido em {{ \Carbon\Carbon::now()->locale('pt_BR')->translatedFormat('d \\d\\e F \\d\\e Y') }}.
        </div>
    </div>

    <div class="signatures">
        <div class="sig">
            <div class="line"></div>
            <div>Coordenador(a) do Curso</div>
        </div>
        <div class="sig">
            <div class="line"></div>
            <div>Diretor(a) Acadêmico(a)</div>
        </div>
    </div>

    <div class="footer">
        Documento: {{ $codigo }} — {{ $instituicao }}
    </div>
</body>
</html>
