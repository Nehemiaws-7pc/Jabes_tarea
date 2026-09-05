<?php
declare(strict_types=1);

// BCMath evita errores binarios de punto flotante al guardar dinero.
final class Money
{
    public const TAX_PERCENT = '12.00';

    public static function price(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^\\d{1,6}(\\.\\d{1,2})?$/D', $value)
            || bccomp($value, '0', 2) <= 0) {
            throw new InvalidArgumentException('El precio USD debe ser positivo, con máximo 6 enteros y 2 decimales.');
        }
        return bcadd($value, '0', 2);
    }

    public static function round(string $value): string
    {
        return bcadd($value, '0.005', 2); // Redondeo HALF_UP; importes no negativos.
    }

    public static function totals(string $subtotal, string $rate): array
    {
        if (bccomp($subtotal, '99999999.99', 2) > 0) {
            throw new InvalidArgumentException('La venta supera el límite académico de USD 99,999,999.99.');
        }
        $tax = self::round(bcmul($subtotal, bcdiv(self::TAX_PERCENT, '100', 4), 6));
        $total = bcadd($subtotal, $tax, 2);
        return [
            'subtotal_usd' => $subtotal, 'tax_percent' => self::TAX_PERCENT,
            'tax_usd' => $tax, 'total_usd' => $total,
            'total_gtq' => self::round(bcmul($total, $rate, 8)),
        ];
    }
}

