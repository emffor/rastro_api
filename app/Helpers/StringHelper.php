<?php

namespace App\Helpers;

class StringHelper
{
    /**
     * Remove acentos de uma string.
     */
    public static function removerAcentos(string $string): string
    {
        $acentos = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ];

        return strtr($string, $acentos);
    }

    /**
     * Gera um email válido (sem acentos, espaços, caracteres especiais).
     */
    public static function gerarEmail(string $nome, string $dominio = 'teste.com'): string
    {
        $email = self::removerAcentos($nome);
        $email = strtolower($email);
        $email = preg_replace('/[^a-z0-9.]/', '', str_replace(' ', '.', $email));
        $email = preg_replace('/\.+/', '.', $email);
        $email = trim($email, '.');

        return "{$email}@{$dominio}";
    }

    /**
     * Formata CPF: 000.000.000-00
     */
    public static function formatarCpf(string $cpf): string
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    /**
     * Formata CNPJ: 00.000.000/0000-00
     */
    public static function formatarCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }

    /**
     * Formata telefone: (00) 00000-0000 ou (00) 0000-0000
     */
    public static function formatarTelefone(string $telefone): string
    {
        $telefone = preg_replace('/\D/', '', $telefone);
        
        if (strlen($telefone) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
        }
        
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
    }
}
