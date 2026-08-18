<?php

namespace App\Security;

/**
 * Validasi input chat sesuai DOCS/06 chat request contract.
 */
final class InputValidator
{
    public const MAX_MESSAGE_LENGTH = 2000;

    public const MAX_TURNS = 20;

    /**
     * @return list<string> daftar pelanggaran; kosong = valid.
     */
    public function validateMessage(?string $message): array
    {
        $errors = [];
        $message = $message ?? '';

        if (trim($message) === '') {
            $errors[] = 'INVALID_INPUT';
        }
        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $errors[] = 'INVALID_INPUT';
        }

        return $errors;
    }
}
