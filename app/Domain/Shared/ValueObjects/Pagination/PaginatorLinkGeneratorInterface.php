<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects\Pagination;

/**
 * Interface for pagination link generation.
 *
 * Allows views that expect Laravel's paginator interface to work
 * with domain-independent PaginatedResult VO without coupling the
 * Domain layer to framework classes.
 */
interface PaginatorLinkGeneratorInterface
{
    /**
     * Returns the first item number on the current page, or null if no items.
     */
    public function firstItem(): ?int;

    /**
     * Returns the last item number on the current page, or null if no items.
     */
    public function lastItem(): ?int;

    /**
     * Returns the total number of items across all pages.
     */
    public function total(): int;

    /**
     * Configures how many pages are shown on each side of the current page.
     */
    public function onEachSide(int $sides = 3): self;

    /**
     * Renders pagination links HTML.
     */
    public function links();
}