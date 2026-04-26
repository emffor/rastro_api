<?php

namespace App\Services\Calculators;

class CalculadoraVolumeService
{
    /**
     * Calcular volume em metros cúbicos (Tora).
     */
    public function calcularVolumeTora(float $diametroMenor, float $diametroMaior, float $comprimento): float
    {
        // Fórmula de Smalian
        $areaMenor = pi() * pow($diametroMenor / 2, 2);
        $areaMaior = pi() * pow($diametroMaior / 2, 2);
        
        return ($areaMenor + $areaMaior) / 2 * $comprimento;
    }

    public function calcularVolumeMadeira(float $largura, float $espessura, float $comprimento): float
    {
        return $largura * $espessura * $comprimento;
    }
}
