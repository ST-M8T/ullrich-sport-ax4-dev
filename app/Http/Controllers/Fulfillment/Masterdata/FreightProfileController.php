<?php

namespace App\Http\Controllers\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\FreightProfileService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Controllers\Concerns\MasterdataControllerHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fulfillment\Masterdata\StoreFreightProfileRequest;
use App\Http\Requests\Fulfillment\Masterdata\UpdateFreightProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class FreightProfileController extends Controller
{
    use MasterdataControllerHelpers;

    public function __construct(
        private readonly FreightProfileService $service,
    ) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);
        $search = $this->normaliseSearch($request->query('search'));

        $paginatedResult = $this->service->paginate($perPage, [
            'search' => $search,
        ]);

        return view('fulfillment.masterdata.freight.index', [
            'freightProfiles' => $paginatedResult->items(),
            'paginationLinks' => $paginatedResult->toLinks('fulfillment.masterdata.freight.index'),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): View
    {
        return view('fulfillment.masterdata.freight.create');
    }

    public function store(StoreFreightProfileRequest $request): RedirectResponse
    {
        try {
            $profile = $this->service->create($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Versandprofil konnte nicht erstellt werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.freight.index')
            ->with('success', sprintf('Versandprofil #%d erstellt.', $profile->shippingProfileId()->toInt()));
    }

    public function edit(int $freightProfile): View
    {
        $entity = $this->service->getById(Identifier::fromInt($freightProfile));
        abort_if($entity === null, 404);

        return view('fulfillment.masterdata.freight.edit', [
            'profile' => $entity,
        ]);
    }

    public function update(UpdateFreightProfileRequest $request, int $freightProfile): RedirectResponse
    {
        try {
            $profile = $this->service->update($freightProfile, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Änderungen konnten nicht gespeichert werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.freight.edit', $profile->shippingProfileId()->toInt())
            ->with('success', 'Versandprofil wurde aktualisiert.');
    }

    public function destroy(int $freightProfile): RedirectResponse
    {
        try {
            $this->service->delete($freightProfile);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('fulfillment.masterdata.freight.index')
                ->with('error', 'Versandprofil konnte nicht gelöscht werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.freight.index')
            ->with('success', 'Versandprofil wurde gelöscht.');
    }
}
