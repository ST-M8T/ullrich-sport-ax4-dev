<?php

namespace App\Http\Controllers\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\SenderProfileService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Controllers\Concerns\MasterdataControllerHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fulfillment\Masterdata\StoreSenderProfileRequest;
use App\Http\Requests\Fulfillment\Masterdata\UpdateSenderProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class SenderProfileController extends Controller
{
    use MasterdataControllerHelpers;

    public function __construct(
        private readonly SenderProfileService $service,
    ) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);
        $filters = [
            'search' => $this->normaliseSearch($request->query('search')),
            'country_iso2' => $this->normaliseCountry($request->query('country_iso2')),
        ];

        $paginatedResult = $this->service->paginate($perPage, $filters);

        return view('fulfillment.masterdata.senders.index', [
            'senderProfiles' => $paginatedResult->items(),
            'paginationLinks' => $paginatedResult->toLinks('fulfillment.masterdata.senders.index'),
            'filters' => array_merge($filters, ['per_page' => $perPage]),
        ]);
    }

    public function create(): View
    {
        return view('fulfillment.masterdata.senders.create');
    }

    public function store(StoreSenderProfileRequest $request): RedirectResponse
    {
        try {
            $profile = $this->service->create($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Senderprofil konnte nicht erstellt werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.senders.index')
            ->with('success', sprintf('Senderprofil #%d erstellt.', $profile->id()->toInt()));
    }

    public function edit(int $senderProfile): View
    {
        $entity = $this->service->getById(Identifier::fromInt($senderProfile));
        abort_if($entity === null, 404);

        return view('fulfillment.masterdata.senders.edit', [
            'profile' => $entity,
        ]);
    }

    public function update(UpdateSenderProfileRequest $request, int $senderProfile): RedirectResponse
    {
        try {
            $profile = $this->service->update($senderProfile, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Änderungen konnten nicht gespeichert werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.senders.edit', $profile->id()->toInt())
            ->with('success', 'Senderprofil wurde aktualisiert.');
    }

    public function destroy(int $senderProfile): RedirectResponse
    {
        try {
            $this->service->delete($senderProfile);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('fulfillment.masterdata.senders.index')
                ->with('error', 'Senderprofil konnte nicht gelöscht werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.senders.index')
            ->with('success', 'Senderprofil wurde gelöscht.');
    }

    private function normaliseCountry(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
    }
}
