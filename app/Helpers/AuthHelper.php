<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class AuthHelper
{
    /**
     * Criptografa um ID (seja inteiro ou UUID) para uma string criptografada.
     * Retorna null se falhar ou se o ID for vazio.
     */
    public static function encryptId(int|string|null $id): ?string
    {
        if (empty($id)) {
            return null;
        }
        
        try {
            return Crypt::encryptString((string) $id);
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Descriptografa uma string criptografada de volta para o ID original.
     * Retorna null se for inválido.
     */
    public static function decryptId(?string $hash): int|string|null
    {
        if (empty($hash)) {
            return null;
        }

        try {
            return Crypt::decryptString($hash);
        } catch (DecryptException $e) {
            return null;
        }
    }
}
