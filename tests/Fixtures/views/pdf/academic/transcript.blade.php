<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Histórico Escolar</title>
    <style>
        @page { margin: 20mm 15mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; }
        .header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .logo { width: 48px; height: 48px; }
        .inst { font-size: 16px; font-weight: 700; }
        .doc-title { margin: 6px 0 16px; font-size: 22px; text-align: center; letter-spacing: .4px; }
        .info { margin-bottom: 14px; font-size: 12px; color: #333; display:flex; gap: 20px; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 6px 8px; font-size: 11px; }
        th { background: #f2f6ff; text-align: left; }
        .right { text-align: right; }
        .center { text-align: center; }
        .footer { margin-top: 14px; font-size: 10px; color: #666; }
        .summary { margin-top: 10px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <svg class="logo" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="46" fill="#2166E3" />
            <text x="50" y="58" font-size="32" text-anchor="middle" fill="#fff" font-family="DejaVu Sans, Arial">A</text>
        </svg>
        <div class="inst">{{ $instituicao }}</div>
    </div>

    <div class="doc-title">HISTÓRICO ESCOLAR</div>

    <div class="info">
        <div><strong>Aluno:</strong> {{ $aluno }}</div>
        <div><strong>CPF:</strong> {{ $cpf }}</div>
        <div><strong>Curso:</strong> {{ $curso }} ({{ $titulo }})</div>
        <div><strong>Emitido em:</strong> {{ \Carbon\Carbon::now()->locale('pt_BR')->translatedFormat('d \\d\\e F \\d\\e Y') }}</div>
    </div>

    @php
        $media = 0; $chTotal = 0; $credTotal = 0;
        foreach ($disciplinas as $d) {
            $media += $d['nota'];
            $chTotal += $d['carga_horaria'];
            $credTotal += $d['creditos'];
        }
        $media = count($disciplinas) ? round($media / count($disciplinas), 2) : 0;
    @endphp

    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Código</th>
                <th>Disciplina</th>
                <th class="center" style="width: 10%;">Período</th>
                <th class="right" style="width: 10%;">Créditos</th>
                <th class="right" style="width: 12%;">C.H.</th>
                <th class="right" style="width: 10%;">Nota</th>
                <th class="center" style="width: 14%;">Resultado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($disciplinas as $d)
                <tr>
                    <td>{{ $d['codigo'] }}</td>
                    <td>{{ $d['nome'] }}</td>
                    <td class="center">{{ $d['periodo'] }}</td>
                    <td class="right">{{ $d['creditos'] }}</td>
                    <td class="right">{{ $d['carga_horaria'] }}</td>
                    <td class="right">{{ number_format($d['nota'], 2, ',', '.') }}</td>
                    <td class="center">{{ $d['resultado'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <strong>Média Geral:</strong> {{ number_format($media, 2, ',', '.') }} —
        <strong>Créditos Totais:</strong> {{ $credTotal }} —
        <strong>Carga Horária Total:</strong> {{ $chTotal }}h
    </div>

    <div class="footer">
        Documento: {{ $codigo }} — {{ $instituicao }}
    </div>
</body>
</html>
