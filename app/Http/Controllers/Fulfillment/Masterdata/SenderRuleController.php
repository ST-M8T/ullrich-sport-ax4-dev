<?php

declare(strict_types=1);

namespace App\Http\Controllers\Fulfillment\Masterdata;

use App\Application\Fulfillment\Masterdata\Services\SenderRuleService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fulfillment\Masterdata\StoreSenderRuleRequest;
use App\Http\Requests\Fulfillment\Masterdata\UpdateSenderRuleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class SenderRuleController extends Controller
{
    private const RULE_TYPES = [
        'billing_email_contains' => 'E-Mail enthält',
        'plenty_id_equals' => 'Plenty-ID gleich',
        'customer_id_equals' => 'Kunden-ID gleich',
        'shipping_country_equals' => 'Versandland gleich',
        'order_total_greater' => 'Auftragswert größer',
    ];

    public function __construct(
        private readonly SenderRuleService $service,
    ) {
        // Controller is resolved with these collaborators.
    }

    public function index(Request $request): View
    {
        $listing = $this->service->list($request->query());
        /** @var \App\Domain\Shared\ValueObjects\Pagination\PaginatedResult $paginator */
        $paginator = $listing['paginator'];

        return view('fulfillment.masterdata.sender-rules.index', [
            'rules' => $paginator->items(),
            'paginationLinks' => $paginator->toLinks('fulfillment.masterdata.sender-rules.index'),
            'filters' => $listing['filters'],
            'senderProfiles' => $this->service->senderProfiles(),
            'ruleTypes' => self::RULE_TYPES,
        ]);
    }

    public function create(): View
    {
        return view('fulfillment.masterdata.sender-rules.create', [
            'senderProfiles' => $this->service->senderProfiles(),
            'ruleTypes' => self::RULE_TYPES,
        ]);
    }

    public function store(StoreSenderRuleRequest $request): RedirectResponse
    {
        try {
            $rule = $this->service->create($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Sender-Regel konnte nicht erstellt werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.sender-rules.index')
            ->with('success', sprintf('Sender-Regel #%d erstellt.', $rule->id()->toInt()));
    }

    public function edit(int $senderRule): View
    {
        $entity = $this->service->getById(Identifier::fromInt($senderRule));
        abort_if($entity === null, 404);

        return view('fulfillment.masterdata.sender-rules.edit', [
            'rule' => $entity,
            'senderProfiles' => $this->service->senderProfiles(),
            'ruleTypes' => self::RULE_TYPES,
        ]);
    }

    public function update(UpdateSenderRuleRequest $request, int $senderRule): RedirectResponse
    {
        try {
            $rule = $this->service->update($senderRule, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Änderungen konnten nicht gespeichert werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.sender-rules.edit', $rule->id()->toInt())
            ->with('success', 'Sender-Regel wurde aktualisiert.');
    }

    public function destroy(int $senderRule): RedirectResponse
    {
        try {
            $this->service->delete($senderRule);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('fulfillment.masterdata.sender-rules.index')
                ->with('error', 'Sender-Regel konnte nicht gelöscht werden.');
        }

        return redirect()
            ->route('fulfillment.masterdata.sender-rules.index')
            ->with('success', 'Sender-Regel wurde gelöscht.');
    }
}
