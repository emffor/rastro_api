<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TelefoneValido implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $telefone = preg_replace('/\D/', '', $value);

        // Telefone fixo: 10 dígitos, celular: 11 dígitos
        if (!in_array(strlen($telefone), [10, 11])) {
            $fail('O :attribute deve ter 10 ou 11 dígitos.');
            return;
        }

        // Celular deve começar com 9 após o DDD
        if (strlen($telefone) === 11 && $telefone[2] !== '9') {
            $fail('O :attribute de celular deve começar com 9.');
            return;
        }

        // DDD válido (11 a 99)
        $ddd = (int) substr($telefone, 0, 2);
        if ($ddd < 11 || $ddd > 99) {
            $fail('O DDD do :attribute é inválido.');
        }
    }
}
