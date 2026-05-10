<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Dispatch\DispatchListService;
use App\Application\Dispatch\Queries\EnrichDispatchScans;
use App\Application\Dispatch\Resources\DispatchScanResource;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Requests\Api\Dispatch\CaptureDispatchScanRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

final class DispatchScanController
{
    public function __construct(
        private readonly DispatchListService $service,
        private readonly EnrichDispatchScans $enrichScans,
    ) {}

    public function index(Request $request, int $list): JsonResponse
    {
        $listId = Identifier::fromInt($list);
        $aggregate = $this->service->get($listId);

        if ($aggregate === null) {
            abort(404);
        }

        $enrichedScans = ($this->enrichScans)($aggregate->scans());

        $scans = array_map(
            fn (array $enriched) => DispatchScanResource::fromDomain(
                $enriched['scan'],
                $enriched['username'],
                $enriched['order_details']
            )->toArray(),
            $enrichedScans
        );

        return response()->json([
            'list_id' => $aggregate->id()->toInt(),
            'scan_count' => $aggregate->scanCount(),
            'scans' => $scans,
        ]);
    }

    public function store(CaptureDispatchScanRequest $request, int $list): JsonResponse
    {
        $listId = Identifier::fromInt($list);

        $validated = $request->validated();

        $shipmentOrderId = isset($validated['shipment_order_id'])
            ? Identifier::fromInt((int) $validated['shipment_order_id'])
            : null;
        $capturedByUserId = isset($validated['captured_by_user_id'])
            ? Identifier::fromInt((int) $validated['captured_by_user_id'])
            : null;
        $capturedAt = isset($validated['captured_at'])
            ? new DateTimeImmutable($validated['captured_at'])
            : null;
        $metadata = $validated['metadata'] ?? [];

        try {
            $scan = $this->service->captureScan(
                $listId,
                $validated['barcode'],
                $shipmentOrderId,
                $capturedByUserId,
                $capturedAt,
                $metadata,
            );
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();

            if ($message === 'Dispatch list not found.') {
                return response()->json(['message' => $message], 404);
            }

            if ($message === 'Dispatch list is closed.') {
                return response()->json(['message' => $message], 409);
            }

            throw $exception;
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $status = $message === 'Cannot add scans to a closed dispatch list.' ? 409 : 422;

            return response()->json(['message' => $message], $status);
        }

        $aggregate = $this->service->get($listId);
        $scanCount = $aggregate?->scanCount() ?? 0;

        $enriched = ($this->enrichScans)([$scan]);
        $enrichedScan = $enriched[0] ?? ['scan' => $scan, 'username' => null, 'order_details' => null];

        return response()->json([
            'list_id' => $listId->toInt(),
            'scan_count' => $scanCount,
            'scan' => DispatchScanResource::fromDomain(
                $enrichedScan['scan'],
                $enrichedScan['username'],
                $enrichedScan['order_details']
            )->toArray(),
        ], 201);
    }
}
