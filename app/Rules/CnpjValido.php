<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CnpjValido implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = preg_replace('/\D/', '', $value);

        if (strlen($cnpj) !== 14) {
            $fail('O :attribute deve ter 14 dígitos.');
            return;
        }

        // CNPJs inválidos conhecidos
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            $fail('O :attribute informado é inválido.');
            return;
        }

        // Valida primeiro dígito
        $soma = 0;
        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        for ($i = 0; $i < 12; $i++) {
            $soma += (int) $cnpj[$i] * $pesos1[$i];
        }
        $resto = $soma % 11;
        $digito1 = ($resto < 2) ? 0 : 11 - $resto;

        if ((int) $cnpj[12] !== $digito1) {
            $fail('O :attribute informado é inválido.');
            return;
        }

        // Valida segundo dígito
        $soma = 0;
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        for ($i = 0; $i < 13; $i++) {
            $soma += (int) $cnpj[$i] * $pesos2[$i];
        }
        $resto = $soma % 11;
        $digito2 = ($resto < 2) ? 0 : 11 - $resto;

        if ((int) $cnpj[13] !== $digito2) {
            $fail('O :attribute informado é inválido.');
        }
    }
}
