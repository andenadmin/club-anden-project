<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normaliza un número de teléfono al formato canónico que usamos para `numero_contacto`:
     * solo dígitos, sin '+', sin espacios, sin guiones, sin paréntesis.
     *
     * Ejemplos:
     *   "+54 9 11 0000-0001"   → "5491100000001"
     *   "(11) 0000-0001"       → "1100000001"
     *   "5491100000001"        → "5491100000001"
     *   ""                     → ""
     */
    public static function normalize(?string $raw): string
    {
        if ($raw === null) return '';
        return preg_replace('/\D+/', '', $raw) ?? '';
    }
}
