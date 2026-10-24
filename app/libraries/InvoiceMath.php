<?php

/**
 * The money arithmetic of supplier and customer invoices (tasks 8.7 / 8.8): integer cents only, no float ever reaches the
 * ledger.
 *
 * VAT (unchanged behaviour since the legacy calculateVat(), no VAT account): the VAT is part of the line amount posted.
 * Per line, with G = quantity x rate rounded to the cent: type 1 (no VAT) inclusive = G; type 2 (inclusive) VAT = G x 16 / 116,
 * exclusive = G - VAT; type 3 (exclusive) VAT = G x 16 / 100, inclusive = G + VAT. Every VAT is rounded half up per line and
 * the header holds the sums of the lines, so Dr / Cr balance to the cent.
 */
class InvoiceMath
{
    /** the only VAT rate the invoice maths has ever supported */
    const VAT_RATE = 16;
    /** largest invoice total in cents (DECIMAL(18,2) holds far more; this keeps every product below in integer range) */
    const MAX_CENTS = 9999999999999;

    /** '12.5' -> 1250. Only for strings already accepted by Ledger::amountError(). */
    public static function cents(string $s): int
    {
        $parts = explode('.', $s, 2);
        return ((int) $parts[0]) * 100 + (int) str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** A posted value as a trimmed string, or null when it is not a scalar (an array / object posted by hand). */
    public static function scalar($v): ?string
    {
        return is_scalar($v) || $v === null ? trim((string) $v) : null;
    }

    /** Line gross in cents: quantity x rate rounded half up. */
    public static function gross(int $qtyCents, int $rateCents): int
    {
        return intdiv($qtyCents * $rateCents + 50, 100);
    }

    /** [exclusive, vat, inclusive] cents of one line gross for a VAT type. */
    public static function lineVat(int $vattype, int $gross): array
    {
        if ($vattype === 2) {
            $vat = intdiv($gross * self::VAT_RATE * 2 + (100 + self::VAT_RATE), 2 * (100 + self::VAT_RATE));
            return [$gross - $vat, $vat, $gross];
        }
        if ($vattype === 3) {
            $vat = intdiv($gross * self::VAT_RATE * 2 + 100, 200);
            return [$gross, $vat, $gross + $vat];
        }
        return [$gross, 0, $gross];
    }
}
