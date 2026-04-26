<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Resumo DOF - Pedido {{ $pedido['numero'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 12px;
            font-weight: normal;
        }
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .info-row {
            display: table-row;
        }
        .info-cell {
            display: table-cell;
            padding: 3px 5px;
            border: 1px solid #ccc;
        }
        .info-label {
            font-weight: bold;
            background-color: #f0f0f0;
            width: 100px;
        }
        .section-title {
            background-color: #333;
            color: #fff;
            padding: 5px 10px;
            font-weight: bold;
            margin: 15px 0 5px 0;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.items th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
            font-size: 10px;
        }
        table.items td {
            border: 1px solid #000;
            padding: 5px;
            font-size: 10px;
        }
        table.items .col-num {
            width: 30px;
            text-align: center;
        }
        table.items .col-qtd {
            width: 70px;
            text-align: right;
        }
        table.items .col-un {
            width: 40px;
            text-align: center;
        }
        .totals {
            margin-top: 15px;
            text-align: right;
            font-weight: bold;
        }
        .totals span {
            background-color: #f0f0f0;
            padding: 5px 10px;
            border: 1px solid #000;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            text-align: center;
            color: #666;
        }
        .products-detail {
            font-size: 9px;
            color: #666;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RESUMO PARA EMISSÃO DE DOF</h1>
        <h2>Documento de Origem Florestal - Consumidor Isento de CTF</h2>
    </div>

    <div class="section-title">DADOS DO PEDIDO</div>
    <table class="items">
        <tr>
            <td class="info-label">Nº Pedido</td>
            <td>{{ $pedido['numero'] }}</td>
            <td class="info-label">Data</td>
            <td>{{ \Carbon\Carbon::parse($pedido['data'])->format('d/m/Y') }}</td>
            <td class="info-label">Status</td>
            <td>{{ $pedido['status'] }}</td>
        </tr>
    </table>

    <div class="section-title">EMISSOR (REMETENTE)</div>
    <table class="items">
        <tr>
            <td class="info-label">Nome</td>
            <td colspan="3">{{ $emissor['nome'] }}</td>
        </tr>
        <tr>
            <td class="info-label">CNPJ</td>
            <td colspan="3">{{ $emissor['cnpj'] }}</td>
        </tr>
    </table>

    <div class="section-title">DESTINATÁRIO</div>
    <table class="items">
        <tr>
            <td class="info-label">Nome</td>
            <td colspan="3">{{ $destinatario['nome'] }}</td>
        </tr>
        <tr>
            <td class="info-label">{{ $destinatario['tipo'] === 'PJ' ? 'CNPJ' : 'CPF' }}</td>
            <td>{{ $destinatario['documento'] }}</td>
            <td class="info-label">Telefone</td>
            <td>{{ $destinatario['telefone'] ?? 'N/A' }}</td>
        </tr>
        @if($destinatario['endereco'])
        <tr>
            <td class="info-label">Endereço</td>
            <td colspan="3">{{ $destinatario['endereco'] }}</td>
        </tr>
        @endif
    </table>

    <div class="section-title">ITENS PARA O DOF (Produto / Espécie)</div>
    <table class="items">
        <tr>
            <th class="col-num">Nº</th>
            <th>Produto / Espécie</th>
            <th class="col-qtd">Qtd.</th>
            <th class="col-un">Un.</th>
        </tr>
        @foreach($itens_dof as $index => $item)
        <tr>
            <td class="col-num">{{ $index + 1 }}</td>
            <td>
                {{ $item['tipo_produto'] }}
                @if(!empty($item['produtos']))
                <div class="products-detail">
                    @foreach($item['produtos'] as $prod)
                    → {{ $prod['nome'] }} ({{ $prod['quantidade'] }} {{ $prod['unidade'] }} = {{ number_format($prod['volume_m3'], 4, ',', '.') }} m³)
                    @if(!$loop->last), @endif
                    @endforeach
                </div>
                @endif
            </td>
            <td class="col-qtd">{{ number_format($item['quantidade_m3'], 4, ',', '.') }}</td>
            <td class="col-un">{{ $item['unidade'] }}</td>
        </tr>
        @endforeach
    </table>

    <div class="totals">
        <span>VOLUME TOTAL: {{ number_format($totais['volume_total_m3'], 4, ',', '.') }} m³</span>
    </div>

    @if($observacoes)
    <div class="section-title">OBSERVAÇÕES</div>
    <p style="padding: 10px; border: 1px solid #ccc;">{{ $observacoes }}</p>
    @endif

    <div class="footer">
        <p>Documento gerado em {{ $data_geracao }}</p>
        <p>Este documento é um resumo para auxiliar na emissão do DOF oficial.</p>
    </div>
</body>
</html>
