<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dispatch;

use App\Application\Dispatch\DispatchListService;
use App\Application\Dispatch\Queries\ListDispatchLists;
use App\Application\Dispatch\Resources\DispatchScanResource;
use App\Domain\Shared\ValueObjects\Identifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DispatchListController
{
    public function __construct(
        private readonly ListDispatchLists $listLists,
        private readonly DispatchListService $service,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->trim()->toString();
        $reference = $request->string('reference')->trim()->toString();

        $filters = [];

        if ($status !== '') {
            $filters['status'] = $status;
        }

        if ($reference !== '') {
            $filters['reference'] = $reference;
        }

        if ($request->filled('created_by_user_id')) {
            $filters['created_by_user_id'] = (int) $request->input('created_by_user_id');
        }

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));

        $pagination = ($this->listLists)($page, $perPage, $filters, false);

        return view('dispatch.lists.index', [
            'lists' => $pagination->lists,
            'totalLists' => $pagination->total,
            'page' => $pagination->page,
            'perPage' => $pagination->perPage,
            'totalPages' => $pagination->totalPages(),
            'pagination' => $pagination,
            'statusOptions' => [
                '' => 'Alle',
                'open' => 'Offen',
                'closed' => 'Geschlossen',
                'exported' => 'Exportiert',
            ],
            'filters' => [
                'status' => $status,
                'reference' => $reference,
                'created_by_user_id' => $filters['created_by_user_id'] ?? null,
            ],
        ]);
    }

    public function close(Request $request, int $list): RedirectResponse
    {
        $validated = $request->validate([
            'export_filename' => ['nullable', 'string', 'max:191', 'regex:/\.csv$/i'],
        ]);

        try {
            $listId = Identifier::fromInt($list);
            $userId = $this->resolveUserId($request);
            $exportFilename = $validated['export_filename'] ?? null;

            $updatedList = $this->service->closeList($listId, $userId, $exportFilename ?: null);

            return redirect()
                ->route('dispatch-lists', $request->only(['status', 'reference', 'per_page']))
                ->with('success', sprintf('Dispatch-Liste #%d wurde geschlossen.', $updatedList->id()->toInt()));
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('dispatch-lists', $request->only(['status', 'reference', 'per_page']))
                ->withErrors(['dispatch' => $e->getMessage()])
                ->withInput();
        }
    }

    public function scans(Request $request, int $list): JsonResponse
    {
        $listId = Identifier::fromInt($list);
        $dispatchList = $this->service->get($listId);

        if ($dispatchList === null) {
            abort(404);
        }

        $scans = array_map(
            static fn ($scan) => DispatchScanResource::fromDomain($scan)->toArray(),
            $dispatchList->scans()
        );

        return response()->json([
            'list_id' => $dispatchList->id()->toInt(),
            'scan_count' => $dispatchList->scanCount(),
            'scans' => $scans,
        ]);
    }

    public function export(Request $request, int $list): RedirectResponse
    {
        $validated = $request->validate([
            'export_filename' => ['required', 'string', 'max:191', 'regex:/\.csv$/i'],
        ]);

        try {
            $listId = Identifier::fromInt($list);
            $userId = $this->resolveUserId($request);

            $updatedList = $this->service->exportList($listId, $userId, $validated['export_filename']);

            return redirect()
                ->route('dispatch-lists', $request->only(['status', 'reference', 'per_page']))
                ->with('success', sprintf('Dispatch-Liste #%d wurde exportiert.', $updatedList->id()->toInt()));
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('dispatch-lists', $request->only(['status', 'reference', 'per_page']))
                ->withErrors(['dispatch' => $e->getMessage()])
                ->withInput();
        }
    }

    private function resolveUserId(Request $request): Identifier
    {
        $user = $request->user();
        $userId = $user?->getAuthIdentifier();

        if (! is_numeric($userId)) {
            throw new \RuntimeException('Ein angemeldeter Benutzer ist erforderlich.');
        }

        return Identifier::fromInt((int) $userId);
    }
}
