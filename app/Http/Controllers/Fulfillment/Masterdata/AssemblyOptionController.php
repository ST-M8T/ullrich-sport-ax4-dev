<?php

namespace App\Http\Controllers\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\AssemblyOptionService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Controllers\Concerns\MasterdataControllerHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fulfillment\Masterdata\StoreAssemblyOptionRequest;
use App\Http\Requests\Fulfillment\Masterdata\UpdateAssemblyOptionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class AssemblyOptionController extends Controller
{
    use MasterdataControllerHelpers;

    public function __construct(
        private readonly AssemblyOptionService $service,
    ) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);
        $filters = [
            'assembly_item_id' => $this->normaliseInt($request->query('assembly_item_id')),
            'assembly_packaging_id' => $this->normaliseInt($request->query('assembly_packaging_id')),
            'search' => $this->normaliseSearch($request->query('search')),
        ];

        $paginatedResult = $this->service->paginate($perPage, $filters);

        return view('fulfillment.masterdata.assembly.index', [
            'assemblyOptions' => $paginatedResult->items(),
            'paginationLinks' => $paginatedResult->toLinks('fulfillment.masterdata.assembly.index'),
            'filters' => array_merge($filters, ['per_page' => $perPage]),
            'packagingProfiles' => $this->service->packagingProfiles(),
        ]);
    }

    public function create(): View
    {
        return view('fulfillment.masterdata.assembly.create', [
            'packagingProfiles' => $this->service->packagingProfiles(),
        ]);
    }

    public function store(StoreAssemblyOptionRequest $request): RedirectResponse
    {
        try {
            $option = $this->service->create($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Vormontage konnte nicht angelegt werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.assembly.index')
            ->with('success', sprintf('Vormontage #%d erfolgreich erstellt.', $option->id()->toInt()));
    }

    public function edit(int $assemblyOption): View
    {
        $entity = $this->service->getById(Identifier::fromInt($assemblyOption));
        abort_if($entity === null, 404);

        return view('fulfillment.masterdata.assembly.edit', [
            'option' => $entity,
            'packagingProfiles' => $this->service->packagingProfiles(),
        ]);
    }

    public function update(UpdateAssemblyOptionRequest $request, int $assemblyOption): RedirectResponse
    {
        try {
            $option = $this->service->update($assemblyOption, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Änderungen konnten nicht gespeichert werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.assembly.edit', $option->id()->toInt())
            ->with('success', 'Vormontage wurde aktualisiert.');
    }

    public function destroy(int $assemblyOption): RedirectResponse
    {
        try {
            $this->service->delete($assemblyOption);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('fulfillment.masterdata.assembly.index')
                ->with('error', 'Vormontage konnte nicht gelöscht werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.assembly.index')
            ->with('success', 'Vormontage wurde gelöscht.');
    }
}
