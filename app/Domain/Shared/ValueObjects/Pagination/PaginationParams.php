<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects\Pagination;

/**
 * Value Object for pagination request parameters.
 * Immutable.
 */
final class PaginationParams
{
    public const SORT_DIR_ASC = 'asc';
    public const SORT_DIR_DESC = 'desc';

    private function __construct(
        private readonly int $page,
        private readonly int $perPage,
        private readonly ?string $sortBy,
        private readonly string $sortDir,
    ) {}

    public static function create(
        int $page = 1,
        int $perPage = Page::DEFAULT_SIZE,
        ?string $sortBy = null,
        string $sortDir = self::SORT_DIR_DESC,
    ): self {
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < Page::MIN_SIZE) {
            $perPage = Page::MIN_SIZE;
        }
        if ($perPage > Page::MAX_SIZE) {
            $perPage = Page::MAX_SIZE;
        }
        $sortDir = strtolower($sortDir) === self::SORT_DIR_ASC ? self::SORT_DIR_ASC : self::SORT_DIR_DESC;

        return new self($page, $perPage, $sortBy, $sortDir);
    }

    public static function fromPageAndSize(int $page, int $perPage): self
    {
        return self::create($page, $perPage);
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function sortBy(): ?string
    {
        return $this->sortBy;
    }

    public function sortDir(): string
    {
        return $this->sortDir;
    }

    public function sortDirIsAsc(): bool
    {
        return $this->sortDir === self::SORT_DIR_ASC;
    }

    public function toPage(): Page
    {
        return Page::create($this->page, $this->perPage);
    }
}
