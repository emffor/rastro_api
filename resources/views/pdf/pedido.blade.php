<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $pedido->status === 'ORCAMENTO' ? 'Orçamento' : 'Pedido' }} {{ $pedido->numero }}</title>
    <style>
        @page {
            margin: 15px 20px 80px 20px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 9px;
            color: #333;
            line-height: 1.3;
        }

        /* Header */
        .header-container {
            width: 100%;
            border: 0.5px solid #ccc;
            margin-bottom: 10px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
            padding: 8px;
        }
        .logo-cell {
            width: 96px;
            text-align: center;
            border-right: 0.5px solid #ccc;
        }
        .logo-cell img {
            max-width: 84px;
            max-height: 60px;
        }
        .company-cell {
            padding-left: 10px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #000;
            margin-bottom: 3px;
        }
        .company-info {
            font-size: 8px;
            color: #555;
            line-height: 1.4;
        }
        .doc-info-cell {
            width: 100px;
            text-align: center;
            border-left: 0.5px solid #ccc;
        }
        .doc-type {
            font-size: 10px;
            font-weight: bold;
            color: #0070C0;
            margin-bottom: 5px;
        }
        .doc-number {
            font-size: 16px;
            font-weight: bold;
            color: #000;
        }
        .doc-date {
            font-size: 8px;
            color: #666;
            margin-top: 3px;
        }

        /* Section Title */
        .section-title {
            background: #E0E0E0;
            border: 0.5px solid #ccc;
            padding: 5px 8px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #333;
            margin-bottom: 0;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table td {
            border: 0.5px solid #ccc;
            padding: 5px 8px;
            vertical-align: top;
        }
        .field-label {
            font-size: 7px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .field-value {
            font-size: 9px;
            color: #000;
            font-weight: 500;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .items-table th {
            background: #0070C0;
            color: #fff;
            border: 0.5px solid #0070C0;
            padding: 6px 5px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }
        .items-table td {
            border: 0.5px solid #ccc;
            padding: 5px;
            font-size: 9px;
            vertical-align: middle;
        }
        .items-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }

        /* Totals */
        .totals-container {
            width: 100%;
            margin-bottom: 15px;
        }
        .totals-table {
            width: 250px;
            float: right;
            border-collapse: collapse;
        }
        .totals-table td {
            border: 0.5px solid #ccc;
            padding: 5px 8px;
            font-size: 9px;
        }
        .totals-table .label {
            background: #E0E0E0;
            text-align: right;
            font-weight: bold;
        }
        .totals-table .value {
            text-align: center;
            font-weight: bold;
        }
        .totals-table .total-row td {
            background: #0070C0;
            color: #fff;
            font-size: 11px;
        }

        /* Observations */
        .obs-container {
            clear: both;
            border: 0.5px solid #ccc;
            margin-bottom: 15px;
        }
        .obs-header {
            background: #E0E0E0;
            padding: 5px 8px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 0.5px solid #ccc;
        }
        .obs-content {
            padding: 8px;
            font-size: 9px;
            min-height: 30px;
        }

        /* Signatures */
        .signatures-table {
            width: 100%;
            margin-top: 30px;
        }
        .signatures-table td {
            width: 45%;
            text-align: center;
            padding-top: 25px;
            border-top: 1px solid #333;
            font-size: 9px;
        }
        .signatures-table td.spacer {
            width: 10%;
            border: none;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0px;
            left: 0px;
            right: 0px;
            height: 65px;
            border-top: 0.5px solid #ccc;
            padding: 8px 20px;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table td {
            vertical-align: middle;
        }
        .footer-info {
            font-size: 7px;
            color: #555;
            line-height: 1.5;
        }
        .footer-brand {
            font-size: 9px;
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        .footer-hash {
            font-size: 6px;
            color: #888;
            word-break: break-all;
        }
        .footer-page {
            text-align: right;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    @php
        $logoFullUrl = $empresa->logo_url ? env('MINIO_ENDPOINT') . $empresa->logo_url : null;
        $docHash = strtoupper(substr(md5($pedido->id . $pedido->numero), 0, 16));
    @endphp

    <!-- Footer (fixed) -->
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td width="85%">
                    <div class="footer-brand">{{ $empresa->nome ?? 'Madeira Legal' }}</div>
                    <div class="footer-info">
                        CNPJ: {{ $empresa->cnpj ?? '-' }} 
                        @if($empresa->inscricao_estadual)| IE: {{ $empresa->inscricao_estadual }}@endif
                        <br>
                        Emitido em: {{ now()->format('d/m/Y H:i:s') }}<br>
                        Autenticação: <span class="footer-hash">{{ $docHash }}</span>
                    </div>
                </td>
                <td width="15%" class="footer-page">
                    Documento válido<br>para fins comerciais
                </td>
            </tr>
        </table>
    </div>

    <!-- Header -->
    <div class="header-container">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if($logoFullUrl)
                        <img src="{{ $logoFullUrl }}" alt="Logo">
                    @else
                        <div style="font-size: 24px; color: #0070C0; font-weight: bold;">🪵</div>
                    @endif
                </td>
                <td class="company-cell">
                    <div class="company-name">{{ $empresa->nome ?? 'MADEIRA LEGAL' }}</div>
                    <div class="company-info">
                        @if($empresa->cnpj)CNPJ: {{ $empresa->cnpj }} @if($empresa->inscricao_estadual)| IE: {{ $empresa->inscricao_estadual }}@endif<br>@endif
                        @if($empresa->endereco){{ $empresa->endereco }}@endif
                        @if($empresa->cidade), {{ $empresa->cidade }}@endif
                        @if($empresa->estado) - {{ $empresa->estado }}@endif
                        @if($empresa->cep) | CEP: {{ $empresa->cep }}@endif
                        <br>
                        @if($empresa->telefone)Tel: {{ $empresa->telefone }} | @endif
                        @if($empresa->email){{ $empresa->email }}@endif
                    </div>
                </td>
                <td class="doc-info-cell">
                    <div class="doc-type">{{ $pedido->status === 'ORCAMENTO' ? 'ORÇAMENTO' : 'PEDIDO' }}</div>
                    <div class="doc-number">Nº {{ substr($pedido->numero, -5) }}</div>
                    <div class="doc-date">{{ now()->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Client Section -->
    <div class="section-title">Dados do Cliente</div>
    <table class="data-table">
        <tr>
            <td width="60%">
                <div class="field-label">Cliente</div>
                <div class="field-value">{{ strtoupper($pedido->cliente->nome ?? '-') }}</div>
            </td>
            <td width="40%">
                <div class="field-label">CPF/CNPJ</div>
                <div class="field-value">{{ $pedido->cliente->documento ?? '-' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="field-label">Contato</div>
                <div class="field-value">
                    {{ $pedido->cliente->telefone ?? '-' }}
                    @if($pedido->cliente->email) | {{ $pedido->cliente->email }}@endif
                </div>
            </td>
            <td>
                <div class="field-label">Vendedor</div>
                <div class="field-value">{{ strtoupper($pedido->vendedor->name ?? 'NÃO IDENTIFICADO') }}</div>
            </td>
        </tr>
    </table>

    <!-- Items Section -->
    <div class="section-title">{{ $pedido->status === 'ORCAMENTO' ? 'Orçamento' : 'Itens do Pedido' }}</div>
    <table class="items-table">
        <thead>
            <tr>
                <th width="8%">Item</th>
                <th width="42%">Produto / Serviço</th>
                <th width="10%">Unid.</th>
                <th width="12%">Qtd.</th>
                <th width="13%">Valor Unit.</th>
                <th width="15%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedido->itens as $index => $item)
            <tr>
                <td class="text-center">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ strtoupper($item->produto->nome_formatado ?? $item->produto->nome ?? 'ITEM') }}</td>
                <td class="text-center">{{ $item->produto->unidade ?? 'UN' }}</td>
                <td class="text-center">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                <td class="text-right text-bold">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-container">
        <table class="totals-table">
            <tr>
                <td class="label">SUBTOTAL:</td>
                <td class="value">R$ {{ number_format($pedido->valor_total, 2, ',', '.') }}</td>
            </tr>
            @if($pedido->desconto > 0)
            <tr>
                <td class="label" style="color: #c00;">DESCONTO:</td>
                <td class="value" style="color: #c00;">- R$ {{ number_format($pedido->desconto, 2, ',', '.') }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="label" style="background: #0070C0;">TOTAL:</td>
                <td class="value">R$ {{ number_format($pedido->valor_final ?? $pedido->valor_total, 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <!-- Observations -->
    <div class="obs-container" style="clear: both;">
        <div class="obs-header">Observações</div>
        <div class="obs-content">
            @if($pedido->observacao)
                {{ strtoupper($pedido->observacao) }}
            @else
                <span style="color: #999; font-style: italic;">Sem observações.</span>
            @endif
        </div>
    </div>

    <!-- Signatures -->
    <table class="signatures-table">
        <tr>
            <td>{{ $empresa->nome ?? 'Madeira Legal' }}</td>
            <td class="spacer"></td>
            <td>{{ $pedido->cliente->nome ?? 'Cliente' }}</td>
        </tr>
    </table>

</body>
</html>
