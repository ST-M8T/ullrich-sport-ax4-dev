<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use App\Domain\Monitoring\Contracts\DatabaseHealthProbe;
use Illuminate\Support\Facades\DB;

final class DatabaseConnectionHealthProbe implements DatabaseHealthProbe
{
    public function ping(string $connection): void
    {
        DB::connection($connection)->getPdo();
    }
}
