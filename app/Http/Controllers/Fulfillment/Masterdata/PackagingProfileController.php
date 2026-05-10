<?php

namespace App\Http\Controllers\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\PackagingProfileService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Controllers\Concerns\MasterdataControllerHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fulfillment\Masterdata\StorePackagingProfileRequest;
use App\Http\Requests\Fulfillment\Masterdata\UpdatePackagingProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class PackagingProfileController extends Controller
{
    use MasterdataControllerHelpers;

    public function __construct(
        private readonly PackagingProfileService $service,
    ) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);
        $search = $this->normaliseSearch($request->query('search'));

        $paginatedResult = $this->service->paginate($perPage, [
            'search' => $search,
        ]);

        return view('fulfillment.masterdata.packaging.index', [
            'packagingProfiles' => $paginatedResult->items(),
            'paginationLinks' => $paginatedResult->toLinks('fulfillment.masterdata.packaging.index'),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): View
    {
        return view('fulfillment.masterdata.packaging.create');
    }

    public function store(StorePackagingProfileRequest $request): RedirectResponse
    {
        try {
            $profile = $this->service->create($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Verpackungsprofil konnte nicht erstellt werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.packaging.index')
            ->with('success', sprintf('Verpackungsprofil #%d erfolgreich erstellt.', $profile->id()->toInt()));
    }

    public function edit(int $packagingProfile): View
    {
        $entity = $this->service->getById(Identifier::fromInt($packagingProfile));

        abort_if($entity === null, 404);

        return view('fulfillment.masterdata.packaging.edit', [
            'profile' => $entity,
        ]);
    }

    public function update(UpdatePackagingProfileRequest $request, int $packagingProfile): RedirectResponse
    {
        try {
            $profile = $this->service->update($packagingProfile, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Änderungen konnten nicht gespeichert werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.packaging.edit', $profile->id()->toInt())
            ->with('success', 'Verpackungsprofil wurde aktualisiert.');
    }

    public function destroy(int $packagingProfile): RedirectResponse
    {
        try {
            $this->service->delete($packagingProfile);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('fulfillment.masterdata.packaging.index')
                ->with('error', 'Verpackungsprofil konnte nicht gelöscht werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.packaging.index')
            ->with('success', 'Verpackungsprofil wurde gelöscht.');
    }
}
