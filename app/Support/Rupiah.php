<?php

namespace App\Support;

class Rupiah
{
    public static function format(int|float|string|null $amount): string
    {
        return 'Rp '.number_format((int) $amount, 0, ',', '.');
    }
}
