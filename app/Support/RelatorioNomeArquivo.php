<?php

namespace App\Support;

class RelatorioNomeArquivo
{
    public static function montar(string $prefixo, string $empresaNome, string $extensao): string
    {
        $empresa = self::normalizarEmpresa($empresaNome);

        return $prefixo
            . ($empresa !== '' ? '-' . $empresa : '')
            . '-' . now()->format('Y-m-d-His')
            . '.' . ltrim($extensao, '.');
    }

    private static function normalizarEmpresa(string $valor): string
    {
        $valor = trim($valor);

        if ($valor === '' || $valor === '—') {
            return '';
        }

        $semAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
        $normalizado = strtolower((string) ($semAcentos ?: $valor));
        $normalizado = preg_replace('/[^a-z0-9]+/', '-', $normalizado) ?: '';

        return trim($normalizado, '-');
    }
}
