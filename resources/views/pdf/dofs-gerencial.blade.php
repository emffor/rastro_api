<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Gerencial de DOFs</title>
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
        .meta-row {
            margin-top: 2px;
        }
        .kpis {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
        }
        .kpis td {
            padding: 6px 8px;
            width: 20%;
            border-right: 1px solid #f1f5f9;
        }
        .kpis td:last-child {
            border-right: none;
        }
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
        .kpi-muted {
            color: #6b7280;
            font-size: 8px;
            margin-top: 2px;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.8px;
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
        .status {
            font-size: 8px;
            color: #4b5563;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 8px;
            font-size: 8px;
            color: #6b7280;
            text-align: right;
        }
        .legend {
            margin-top: 6px;
            font-size: 8px;
            color: #6b7280;
            text-align: left;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="header-content">
                <h1 class="title">Relatório Gerencial de DOFs</h1>
                <p class="subtitle">Documentos de Origem Florestal - Controle de volume e alocação</p>
                <div class="meta">
                    <div class="meta-row">Empresa: {{ $empresa_nome !== '' ? $empresa_nome : '—' }}</div>
                    <div class="meta-row">Gerado em: {{ $data_geracao }}</div>
                    <div class="meta-row">Filtro: {{ $filtro_busca !== '' ? $filtro_busca : 'Sem filtro' }}</div>
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
                <div class="kpi-label">DOFs</div>
                <div class="kpi-value">{{ $resumo['total_dofs'] }}</div>
            </td>
            <td>
                <div class="kpi-label">Volume Total</div>
                <div class="kpi-value">{{ number_format($resumo['volume_total_m3'], 4, ',', '.') }} m³</div>
            </td>
            <td>
                <div class="kpi-label">Volume Alocado</div>
                <div class="kpi-value">{{ number_format($resumo['volume_alocado_m3'], 4, ',', '.') }} m³</div>
            </td>
            <td>
                <div class="kpi-label">Saldo</div>
                <div class="kpi-value">{{ number_format($resumo['volume_saldo_m3'], 4, ',', '.') }} m³</div>
            </td>
            <td>
                <div class="kpi-label">Distribuição</div>
                <div class="kpi-value">{{ number_format($resumo['percentual_alocado'], 2, ',', '.') }}%</div>
                <div class="kpi-muted">
                    NA {{ $resumo['dofs_ativos'] }} | P {{ $resumo['dofs_parciais'] }} | A {{ $resumo['dofs_encerrados'] }} | V {{ $resumo['dofs_vencidos'] }}
                </div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Número</th>
                <th>Origem</th>
                <th>Destino</th>
                <th>Validade</th>
                <th>Status</th>
                <th class="num">Volume total (m³)</th>
                <th class="num">Volume alocado (m³)</th>
                <th class="num">Saldo (m³)</th>
                <th class="num">% alocado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dofs as $dof)
                @php
                    $total = (float) $dof->volume_total;
                    $saldo = (float) $dof->volume_saldo_m3;
                    $alocado = max(0, $total - $saldo);
                    $percentual = $total > 0 ? ($alocado / $total) * 100 : 0;
                    $unidade = $dof->unidade_medida ?? 'm³';
                    $statusLabel = match ($dof->status) {
                        'ATIVO' => 'NÃO ALOCADO',
                        'PARCIAL' => 'PARCIALMENTE ALOCADO',
                        'ENCERRADO' => 'ALOCADO',
                        default => $dof->status,
                    };
                @endphp
                <tr>
                    <td>{{ $dof->numero }}</td>
                    <td>{{ $dof->origem ?: '—' }}</td>
                    <td>{{ $dof->destino ?: '—' }}</td>
                    <td>{{ optional($dof->valido_ate)->format('d/m/Y H:i') }}</td>
                    <td class="status">{{ $statusLabel }}</td>
                    <td class="num">{{ number_format($total, 4, ',', '.') }}</td>
                    <td class="num">{{ number_format($alocado, 4, ',', '.') }}</td>
                    <td class="num">{{ number_format($saldo, 4, ',', '.') }}</td>
                    <td class="num">{{ number_format($percentual, 2, ',', '.') }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">Nenhum DOF encontrado para os filtros informados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="legend">
        Legenda: NA = Não alocados | P = Parcialmente alocados | A = Alocados | V = DOFs vencidos
    </div>
    <div class="footer">Total de registros: {{ $dofs->count() }}</div>
</body>
</html>
