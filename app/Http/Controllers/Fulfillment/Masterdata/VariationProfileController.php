<?php

namespace App\Http\Controllers\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\VariationProfileService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Controllers\Concerns\MasterdataControllerHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fulfillment\Masterdata\StoreVariationProfileRequest;
use App\Http\Requests\Fulfillment\Masterdata\UpdateVariationProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class VariationProfileController extends Controller
{
    use MasterdataControllerHelpers;

    public function __construct(
        private readonly VariationProfileService $service,
    ) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);
        $filters = [
            'item_id' => $this->normaliseInt($request->query('item_id')),
            'variation_id' => $this->normaliseIntAllowZero($request->query('variation_id')),
            'default_state' => $this->normaliseSearch($request->query('default_state')),
            'search' => $this->normaliseSearch($request->query('search')),
        ];

        $paginatedResult = $this->service->paginate($perPage, $filters);

        return view('fulfillment.masterdata.variations.index', [
            'variationProfiles' => $paginatedResult->items(),
            'paginationLinks' => $paginatedResult->toLinks('fulfillment.masterdata.variations.index'),
            'filters' => array_merge($filters, ['per_page' => $perPage]),
            'packagingProfiles' => $this->service->packagingProfiles(),
            'assemblyOptions' => $this->service->assemblyOptions(),
        ]);
    }

    public function create(): View
    {
        return view('fulfillment.masterdata.variations.create', [
            'packagingProfiles' => $this->service->packagingProfiles(),
            'assemblyOptions' => $this->service->assemblyOptions(),
        ]);
    }

    public function store(StoreVariationProfileRequest $request): RedirectResponse
    {
        try {
            $profile = $this->service->create($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Variantenprofil konnte nicht erstellt werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.variations.index')
            ->with('success', sprintf('Variantenprofil #%d erstellt.', $profile->id()->toInt()));
    }

    public function edit(int $variationProfile): View
    {
        $entity = $this->service->getById(Identifier::fromInt($variationProfile));
        abort_if($entity === null, 404);

        return view('fulfillment.masterdata.variations.edit', [
            'profile' => $entity,
            'packagingProfiles' => $this->service->packagingProfiles(),
            'assemblyOptions' => $this->service->assemblyOptions(),
        ]);
    }

    public function update(UpdateVariationProfileRequest $request, int $variationProfile): RedirectResponse
    {
        try {
            $profile = $this->service->update($variationProfile, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Änderungen konnten nicht gespeichert werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.variations.edit', $profile->id()->toInt())
            ->with('success', 'Variantenprofil wurde aktualisiert.');
    }

    public function destroy(int $variationProfile): RedirectResponse
    {
        try {
            $this->service->delete($variationProfile);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('fulfillment.masterdata.variations.index')
                ->with('error', 'Variantenprofil konnte nicht gelöscht werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.variations.index')
            ->with('success', 'Variantenprofil wurde gelöscht.');
    }

    private function normaliseIntAllowZero(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
