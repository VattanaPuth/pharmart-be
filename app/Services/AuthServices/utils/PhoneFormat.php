<?php

namespace App\Services\AuthServices\utils;

class PhoneFormat
{
    public function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\s+/', '', $phone) ?? ''; // \s = one whitespace character, \s+ = one or more whitespace character

        if (str_starts_with($normalized, '+')) {
            return $normalized;
        }

        if (str_starts_with($normalized, '0')) {
            return '+855' . substr($normalized, 1);
        }

        return $normalized;
    }
}
