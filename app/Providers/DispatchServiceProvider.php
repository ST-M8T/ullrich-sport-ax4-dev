<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Dispatch\DispatchListService;
use App\Application\Dispatch\Queries\ListDispatchLists;
use App\Domain\Dispatch\Contracts\DispatchListRepository;
use App\Infrastructure\Persistence\Dispatch\Eloquent\EloquentDispatchListRepository;
use Illuminate\Support\ServiceProvider;

final class DispatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DispatchListRepository::class, EloquentDispatchListRepository::class);

        $this->app->singleton(ListDispatchLists::class);
        $this->app->singleton(DispatchListService::class);
    }
}
