<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final readonly class Periodo
{
    public function __construct(
        public CarbonImmutable $inicio,
        public CarbonImmutable $fim,
        public CarbonImmutable $vencimento,
    ) {}
}
