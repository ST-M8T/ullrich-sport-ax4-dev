<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderRuleRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderRule;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\Concerns\FlushesMasterdataCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentFulfillmentSenderRuleRepository implements FulfillmentSenderRuleRepository
{
    use FlushesMasterdataCache;

    public function all(): iterable
    {
        return $this->mapCollection(
            FulfillmentSenderRuleModel::query()
                ->ordered()
                ->get()
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<FulfillmentSenderRule>
     */
    public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
    {
        $query = FulfillmentSenderRuleModel::query()
            ->ordered();

        if (isset($filters['target_sender_id'])) {
            $query->where('target_sender_id', (int) $filters['target_sender_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (isset($filters['rule_type'])) {
            $query->where('rule_type', $filters['rule_type']);
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && trim($search) !== '') {
            $query->where('match_value', 'like', '%'.trim($search).'%');
        }

        $paginator = $query->paginate($perPage);

        /** @var array<FulfillmentSenderRule> $items */
        $items = [];
        foreach ($paginator->items() as $model) {
            $items[] = $this->mapModel($model);
        }

        return PaginatedResult::create(
            items: $items,
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
        );
    }

    public function findBySender(Identifier $senderId): iterable
    {
        return $this->mapCollection(
            FulfillmentSenderRuleModel::query()
                ->where('target_sender_id', $senderId->toInt())
                ->orderBy('priority')
                ->get()
        );
    }

    public function getById(Identifier $id): ?FulfillmentSenderRule
    {
        $model = FulfillmentSenderRuleModel::find($id->toInt());

        return $model ? $this->mapModel($model) : null;
    }

    public function create(array $attributes): FulfillmentSenderRule
    {
        $model = new FulfillmentSenderRuleModel;
        $model->fill($attributes);
        $model->save();

        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function update(Identifier $id, array $attributes): FulfillmentSenderRule
    {
        /** @var FulfillmentSenderRuleModel|null $model */
        $model = FulfillmentSenderRuleModel::find($id->toInt());
        if ($model === null) {
            throw (new ModelNotFoundException)->setModel(FulfillmentSenderRuleModel::class, [$id->toInt()]);
        }

        $model->fill($attributes);
        $model->save();

        $model->refresh();

        $this->flushMasterdataCatalogCache();

        return $this->mapModel($model);
    }

    public function delete(Identifier $id): void
    {
        FulfillmentSenderRuleModel::whereKey($id->toInt())->delete();
        $this->flushMasterdataCatalogCache();
    }

    /**
     * @param  Collection<int, FulfillmentSenderRuleModel>  $collection
     * @return iterable<FulfillmentSenderRule>
     */
    private function mapCollection(Collection $collection): iterable
    {
        return $collection->map(fn (FulfillmentSenderRuleModel $model) => $this->mapModel($model));
    }

    private function mapModel(FulfillmentSenderRuleModel $model): FulfillmentSenderRule
    {
        return FulfillmentSenderRule::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            (int) $model->priority,
            $model->rule_type,
            $model->match_value,
            Identifier::fromInt((int) $model->target_sender_id),
            (bool) $model->is_active,
            $model->description,
        );
    }
}
