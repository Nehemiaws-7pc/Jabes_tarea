<?php
declare(strict_types=1);

final class Validation
{
    public static function text(mixed $value, string $field, int $max): string
    {
        if (!is_string($value) || trim($value) === '' || strlen(trim($value)) > $max) {
            throw new InvalidArgumentException("$field es obligatorio; máximo $max bytes.");
        }
        return trim($value);
    }

    public static function id(mixed $value, string $field = 'ID', int $max = 2147483647): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || $value < 1 || $value > $max) {
            throw new InvalidArgumentException("$field debe ser un entero entre 1 y $max.");
        }
        return (int) $value;
    }
}

