<?php

declare(strict_types=1);

namespace App\Application\Dispatch\Resources;

use App\Domain\Dispatch\DispatchListPaginationResult;

final class DispatchListPaginationResource
{
    /**
     * @param  array<int,DispatchListResource>  $lists
     */
    private function __construct(
        private readonly array $lists,
        private readonly int $page,
        private readonly int $perPage,
        private readonly int $total,
        private readonly int $totalPages,
    ) {}

    public static function fromResult(DispatchListPaginationResult $pagination, bool $includeScans = true): self
    {
        $lists = array_map(
            static fn ($list) => DispatchListResource::fromDomain($list, $includeScans),
            $pagination->lists
        );

        return new self(
            $lists,
            $pagination->page,
            $pagination->perPage,
            $pagination->total,
            $pagination->totalPages(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(
                static fn (DispatchListResource $resource) => $resource->toArray(),
                $this->lists
            ),
            'meta' => [
                'page' => $this->page,
                'per_page' => $this->perPage,
                'total' => $this->total,
                'total_pages' => $this->totalPages,
            ],
        ];
    }
}
