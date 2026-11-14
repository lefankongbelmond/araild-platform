<?php

namespace App\Support;

/** « Devise » neutre pour les graphiques de NOMBRES (x-tenant.month-chart attend minor_unit + format()). */
final class CountUnit
{
    public int $minor_unit = 0;

    public function format(int $n): string
    {
        return number_format($n, 0, ',', ' ');
    }
}
