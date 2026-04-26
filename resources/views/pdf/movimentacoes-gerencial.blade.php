<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Movimentações</title>
    <style>
        @page { margin: 16px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            color: #1f2937;
        }
        .header {
            width: 100%;
            margin-bottom: 10px;
        }
        .brand {
            width: 118px;
            vertical-align: top;
            text-align: right;
        }
        .brand img {
            display: block;
            width: 108px;
            height: auto;
            margin-left: auto;
        }
        .header-content {
            vertical-align: top;
        }
        .title {
            font-size: 14px;
            font-weight: 700;
            margin: 0;
        }
        .subtitle {
            margin: 1px 0 0;
            color: #6b7280;
            font-size: 9px;
        }
        .meta {
            margin-top: 5px;
            color: #4b5563;
            font-size: 8.5px;
        }
        .meta-row { margin-top: 2px; }
        .kpis {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
        }
        .kpis td {
            padding: 6px 8px;
            width: 16.66%;
            border-right: 1px solid #f1f5f9;
        }
        .kpis td:last-child { border-right: none; }
        .kpi-label {
            font-size: 7.5px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .02em;
        }
        .kpi-value {
            margin-top: 3px;
            font-size: 11px;
            font-weight: 700;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.2px;
            table-layout: fixed;
        }
        table.data th, table.data td {
            border: 1px solid #e5e7eb;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data th {
            background: #fafafa;
            text-align: left;
            font-weight: 600;
            color: #374151;
        }
        .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .col-tipo { width: 7%; }
        .col-dof { width: 10%; }
        .col-nf { width: 10%; }
        .col-lote { width: 8%; }
        .col-anexos { width: 5%; }
        .col-especie { width: 17%; }
        .col-produtos { width: 16%; }
        .col-volume { width: 8%; }
        .col-usuario { width: 10%; }
        .col-data { width: 9%; }
        .footer {
            margin-top: 8px;
            font-size: 8px;
            color: #6b7280;
            text-align: right;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="header-content">
                <h1 class="title">Relatório de Movimentações</h1>
                <p class="subtitle">Histórico de movimentações de volume DOF</p>
                <div class="meta">
                    <div class="meta-row">Empresa: {{ $empresa_nome !== '' ? $empresa_nome : '—' }}</div>
                    <div class="meta-row">Gerado em: {{ $data_geracao }}</div>
                    <div class="meta-row">Busca: {{ $filtro_busca !== '' ? $filtro_busca : 'Sem filtro' }}</div>
                    <div class="meta-row">Tipo: {{ $filtro_tipo !== '' ? $filtro_tipo : 'Todos' }}</div>
                </div>
            </td>
            <td class="brand">
                @if(file_exists($logo_path))
                    <img src="{{ $logo_path }}" alt="Rastro Florestal">
                @endif
            </td>
        </tr>
    </table>

    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Registros</div>
                <div class="kpi-value">{{ $resumo['total_registros'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Volume total</div>
                <div class="kpi-value">{{ number_format($resumo['volume_total_m3'], 4, ',', '.') }} m³</div>
            </td>
            <td>
                <div class="kpi-label">Entradas</div>
                <div class="kpi-value">{{ $resumo['entradas'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Transferências</div>
                <div class="kpi-value">{{ $resumo['transferencias'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Baixas</div>
                <div class="kpi-value">{{ $resumo['baixas'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Ajustes</div>
                <div class="kpi-value">{{ $resumo['ajustes'] }}</div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th class="col-tipo">Tipo</th>
                <th class="col-dof">DOF</th>
                <th class="col-nf">Nota Fiscal</th>
                <th class="col-lote">Lote Origem</th>
                <th class="col-lote">Lote Destino</th>
                <th class="col-anexos">Anexos</th>
                <th class="col-especie">Item / Espécie</th>
                <th class="col-produtos">Produtos / Peças</th>
                <th class="col-volume num">Volume (m³)</th>
                <th class="col-usuario">Usuário</th>
                <th class="col-data">Data</th>
            </tr>
        </thead>
        <tbody>
            @forelse($linhas as $linha)
                <tr>
                    <td>{{ $linha['tipo'] }}</td>
                    <td>{{ $linha['dof'] }}</td>
                    <td>{{ $linha['nota_fiscal'] }}</td>
                    <td>{{ $linha['lote_origem'] }}</td>
                    <td>{{ $linha['lote_destino'] }}</td>
                    <td>{{ $linha['anexos'] }}</td>
                    <td>{{ $linha['especie'] }}</td>
                    <td>{{ $linha['produtos_pecas'] }}</td>
                    <td class="num">{{ $linha['volume_m3'] }}</td>
                    <td>{{ $linha['usuario'] }}</td>
                    <td>{{ $linha['data'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">Nenhuma movimentação encontrada para os filtros informados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Total de registros: {{ count($linhas) }}</div>
</body>
</html>
