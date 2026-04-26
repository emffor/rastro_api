<?php

namespace App\Support;

class ProdutoDimensionadoEspecieMatcher
{
    private const TIPOS_LEGADOS = [
        'CAVACO',
        'CAIBRO',
        'VIGA',
        'SARRAFO',
        'TABUA',
        'PRANCHA',
        'RIPA',
        'TORA',
        'LENHA',
        'CARVAO',
        'ESTACA',
    ];

    /**
     * @return array<int, string>
     */
    public static function tiposPermitidos(): array
    {
        return self::TIPOS_LEGADOS;
    }

    public static function normalizarTexto(?string $texto): string
    {
        $valor = trim((string) $texto);
        if ($valor === '') {
            return '';
        }

        $valor = mb_strtoupper($valor, 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $normalizado = \Normalizer::normalize($valor, \Normalizer::FORM_D);
            if ($normalizado !== false) {
                $valor = $normalizado;
            }
        }
        $valor = preg_replace('/\p{Mn}+/u', '', $valor) ?? $valor;
        $valor = preg_replace('/\s+/u', ' ', $valor) ?? $valor;

        return trim($valor);
    }

    public static function normalizarTipo(?string $tipo): string
    {
        return self::normalizarTexto($tipo);
    }

    public static function normalizarTipoEspecie(?string $tipo, ?string $nomeTipoFallback = null): string
    {
        $tipoNormalizado = self::normalizarTipo($tipo);
        if ($tipoNormalizado !== '') {
            return $tipoNormalizado;
        }

        return self::tipoDaEspecie($nomeTipoFallback);
    }

    public static function normalizarNomeTipoDescricao(?string $nomeTipo, ?string $tipo): string
    {
        $base = trim((string) $nomeTipo);
        if ($base !== '') {
            $base = preg_replace('/\s*\([^)]*\)\s*$/u', '', $base) ?? $base;
            $base = preg_replace('/\s+/u', ' ', $base) ?? $base;
            $base = trim($base);
        }

        if ($base === '') {
            $base = 'Madeira serrada';
        }

        $tipoDescricao = mb_strtolower(self::normalizarTipo($tipo), 'UTF-8');
        if ($tipoDescricao === '') {
            return $base;
        }

        return "{$base} ({$tipoDescricao})";
    }

    public static function tipoDaEspecie(?string $nomeTipo): string
    {
        $textoNormalizado = self::normalizarTexto($nomeTipo);
        if ($textoNormalizado === '') {
            return '';
        }

        if (preg_match('/\(([^)]+)\)/', $textoNormalizado, $matches)) {
            $candidato = self::normalizarTipo($matches[1] ?? '');
            if ($candidato !== '') {
                return $candidato;
            }
        }

        foreach (self::TIPOS_LEGADOS as $tipo) {
            if (str_contains($textoNormalizado, $tipo)) {
                return $tipo;
            }
        }

        return '';
    }
}
