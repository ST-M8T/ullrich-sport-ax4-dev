<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Contracts;

interface DatabaseHealthProbe
{
    public function ping(string $connection): void;
}
