<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Dispatch\Queries\ListDispatchLists;
use App\Application\Dispatch\Resources\DispatchListPaginationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class DispatchListController
{
    public function __construct(private readonly ListDispatchLists $query) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', 'in:open,closed,exported'],
            'created_by_user_id' => ['sometimes', 'integer', 'min:1'],
            'reference' => ['sometimes', 'string', 'max:191'],
            'include' => ['sometimes', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid query parameters.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 25);

        $filters = array_filter([
            'status' => $data['status'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'reference' => $data['reference'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $includes = collect(explode(',', (string) ($data['include'] ?? '')))
            ->map(static fn ($value) => trim($value))
            ->filter(static fn ($value) => $value !== '')
            ->values();

        $includeScans = $includes->contains('scans');

        $result = ($this->query)($page, $perPage, $filters, $includeScans);

        return response()->json(
            DispatchListPaginationResource::fromResult($result, $includeScans)->toArray()
        );
    }
}
