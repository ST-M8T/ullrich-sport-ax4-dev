<?php

namespace Tests\Feature\Fulfillment;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentAssemblyOptionRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentFreightProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentPackagingProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderRuleRepository;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentVariationProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption;
use App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile;
use App\Domain\Fulfillment\Masterdata\FulfillmentSenderRule;
use App\Domain\Fulfillment\Masterdata\FulfillmentVariationProfile;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use BadMethodCallException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

final class FulfillmentMasterdataPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sections_render_with_domain_data(): void
    {
        // Die Masterdata-Übersicht rendert pro Aufruf nur den aktiven Tab (siehe
        // resources/views/fulfillment/masterdata/partials/catalog.blade.php).
        // Wir prüfen daher jeden Bounded-Context-Tab einzeln gegen die Domain-Fixtures.
        $this->bindRepositoriesWithFixtures();

        $this->signInWithRole('operations');

        // Section-Headings entsprechen den Tab-Labels (siehe
        // FulfillmentMasterdataCatalogPartialComposer und sections/*.blade.php).
        $cases = [
            'packaging' => ['Verpackungen', 'PKG-001', 'Eurobox L'],
            'assembly' => ['Vormontage', '500', 'Preassembly für 500'],
            // defaultState='kit' wird per CSS text-uppercase als KIT gerendert,
            // der DOM-Text bleibt aber 'kit' — assertSeeText prüft DOM, nicht CSS-Rendering.
            'variations' => ['Varianten', 'Variante A', 'kit'],
            // Senders-Section zeigt displayName + senderCode + contactPerson — nicht companyName.
            'sender' => ['Sender', 'sender-berlin', 'Berlin Versandzentrum', 'Max Mustermann'],
            // ruleType 'zip' wird via Str::title() als 'Zip' gerendert; matchValue '10115' steht als <code>.
            'sender-rules' => ['Sender-Regeln', 'Zip', '10115'],
            'freight' => ['Freight-Profile', 'Standard Freight'],
        ];

        foreach ($cases as $tab => $expectedTexts) {
            $response = $this->get('/admin/fulfillment/masterdata?tab='.$tab);
            $response->assertOk();

            foreach ($expectedTexts as $expected) {
                $response->assertSeeText(
                    $expected,
                    "Erwartung verfehlt für Tab '{$tab}': '{$expected}' nicht im Output."
                );
            }
        }
    }

    private function bindRepositoriesWithFixtures(): void
    {
        $packaging = FulfillmentPackagingProfile::hydrate(
            Identifier::fromInt(1),
            'Eurobox L',
            'PKG-001',
            800,
            600,
            400,
            3,
            60,
            45,
            3,
            2,
            'Standardverpackung für große Kits'
        );

        $assembly = FulfillmentAssemblyOption::hydrate(
            Identifier::fromInt(10),
            500,
            Identifier::fromInt(1),
            15.75,
            'Preassembly für 500'
        );

        $variation = FulfillmentVariationProfile::hydrate(
            Identifier::fromInt(100),
            500,
            501,
            'Variante A',
            'kit',
            Identifier::fromInt(1),
            22.4,
            Identifier::fromInt(10)
        );

        $sender = FulfillmentSenderProfile::hydrate(
            Identifier::fromInt(7),
            'sender-berlin',
            'Berlin Versandzentrum',
            'Example GmbH',
            'Max Mustermann',
            'max@example.com',
            '+49 30 123456',
            'Friedrichstraße',
            '12a',
            null,
            '10115',
            'Berlin',
            'DE'
        );

        $rule = FulfillmentSenderRule::hydrate(
            Identifier::fromInt(42),
            1,
            'zip',
            '10115',
            Identifier::fromInt(7),
            true,
            'Berlin Standard'
        );

        $freight = FulfillmentFreightProfile::hydrate(
            Identifier::fromInt(5),
            'Standard Freight'
        );

        $this->app->instance(
            FulfillmentPackagingProfileRepository::class,
            new class([$packaging]) implements FulfillmentPackagingProfileRepository
            {
                public function __construct(private array $items) {}

                public function all(): iterable
                {
                    return $this->items;
                }

                public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
                {
                    $perPage = max(1, $perPage ?? max(1, count($this->items)));
                    $total = count($this->items);
                    $page = 1;
                    $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

                    return PaginatedResult::create(
                        $this->items,
                        $total,
                        $perPage,
                        $page,
                        $lastPage,
                    );
                }

                public function getById(Identifier $id): ?FulfillmentPackagingProfile
                {
                    foreach ($this->items as $item) {
                        if ($item->id()->equals($id)) {
                            return $item;
                        }
                    }

                    return null;
                }

                public function create(array $attributes): FulfillmentPackagingProfile
                {
                    throw new BadMethodCallException('create not implemented in test double');
                }

                public function update(Identifier $id, array $attributes): FulfillmentPackagingProfile
                {
                    throw new BadMethodCallException('update not implemented in test double');
                }

                public function delete(Identifier $id): void
                {
                    throw new BadMethodCallException('delete not implemented in test double');
                }
            }
        );

        $this->app->instance(
            FulfillmentAssemblyOptionRepository::class,
            new class([$assembly]) implements FulfillmentAssemblyOptionRepository
            {
                public function __construct(private array $items) {}

                public function all(): iterable
                {
                    return $this->items;
                }

                public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
                {
                    $perPage = max(1, $perPage ?? max(1, count($this->items)));
                    $total = count($this->items);
                    $page = 1;
                    $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

                    return PaginatedResult::create(
                        $this->items,
                        $total,
                        $perPage,
                        $page,
                        $lastPage,
                    );
                }

                public function findByAssemblyItemId(int $assemblyItemId): ?FulfillmentAssemblyOption
                {
                    foreach ($this->items as $item) {
                        if ($item->assemblyItemId() === $assemblyItemId) {
                            return $item;
                        }
                    }

                    return null;
                }

                public function getById(Identifier $id): ?FulfillmentAssemblyOption
                {
                    foreach ($this->items as $item) {
                        if ($item->id()->equals($id)) {
                            return $item;
                        }
                    }

                    return null;
                }

                public function create(array $attributes): FulfillmentAssemblyOption
                {
                    throw new BadMethodCallException('create not implemented in test double');
                }

                public function update(Identifier $id, array $attributes): FulfillmentAssemblyOption
                {
                    throw new BadMethodCallException('update not implemented in test double');
                }

                public function delete(Identifier $id): void
                {
                    throw new BadMethodCallException('delete not implemented in test double');
                }
            }
        );

        $this->app->instance(
            FulfillmentVariationProfileRepository::class,
            new class([$variation]) implements FulfillmentVariationProfileRepository
            {
                public function __construct(private array $items) {}

                public function all(): iterable
                {
                    return $this->items;
                }

                public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
                {
                    $perPage = max(1, $perPage ?? max(1, count($this->items)));
                    $total = count($this->items);
                    $page = 1;
                    $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

                    return PaginatedResult::create(
                        $this->items,
                        $total,
                        $perPage,
                        $page,
                        $lastPage,
                    );
                }

                public function findByItemId(int $itemId): iterable
                {
                    return array_values(array_filter(
                        $this->items,
                        static fn (FulfillmentVariationProfile $profile) => $profile->itemId() === $itemId
                    ));
                }

                public function getById(Identifier $id): ?FulfillmentVariationProfile
                {
                    foreach ($this->items as $item) {
                        if ($item->id()->equals($id)) {
                            return $item;
                        }
                    }

                    return null;
                }

                public function create(array $attributes): FulfillmentVariationProfile
                {
                    throw new BadMethodCallException('create not implemented in test double');
                }

                public function update(Identifier $id, array $attributes): FulfillmentVariationProfile
                {
                    throw new BadMethodCallException('update not implemented in test double');
                }

                public function delete(Identifier $id): void
                {
                    throw new BadMethodCallException('delete not implemented in test double');
                }
            }
        );

        $this->app->instance(
            FulfillmentSenderProfileRepository::class,
            new class([$sender]) implements FulfillmentSenderProfileRepository
            {
                public function __construct(private array $items) {}

                public function all(): iterable
                {
                    return $this->items;
                }

                public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
                {
                    $perPage = max(1, $perPage ?? max(1, count($this->items)));
                    $total = count($this->items);
                    $page = 1;
                    $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

                    return PaginatedResult::create(
                        $this->items,
                        $total,
                        $perPage,
                        $page,
                        $lastPage,
                    );
                }

                public function findByCode(string $senderCode): ?FulfillmentSenderProfile
                {
                    foreach ($this->items as $item) {
                        if ($item->senderCode() === $senderCode) {
                            return $item;
                        }
                    }

                    return null;
                }

                public function getById(Identifier $id): ?FulfillmentSenderProfile
                {
                    foreach ($this->items as $item) {
                        if ($item->id()->equals($id)) {
                            return $item;
                        }
                    }

                    return null;
                }

                public function create(array $attributes): FulfillmentSenderProfile
                {
                    throw new BadMethodCallException('create not implemented in test double');
                }

                public function update(Identifier $id, array $attributes): FulfillmentSenderProfile
                {
                    throw new BadMethodCallException('update not implemented in test double');
                }

                public function delete(Identifier $id): void
                {
                    throw new BadMethodCallException('delete not implemented in test double');
                }
            }
        );

        $this->app->instance(
            FulfillmentSenderRuleRepository::class,
            new class([$rule]) implements FulfillmentSenderRuleRepository
            {
                public function __construct(private array $items) {}

                public function all(): iterable
                {
                    return $this->items;
                }

                public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
                {
                    $perPage = max(1, $perPage ?? max(1, count($this->items)));
                    $total = count($this->items);
                    $page = 1;
                    $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

                    return PaginatedResult::create(
                        $this->items,
                        $total,
                        $perPage,
                        $page,
                        $lastPage,
                    );
                }

                public function findBySender(Identifier $senderId): iterable
                {
                    return array_values(array_filter(
                        $this->items,
                        static fn (FulfillmentSenderRule $rule) => $rule->targetSenderId()->equals($senderId)
                    ));
                }

                public function getById(Identifier $id): ?FulfillmentSenderRule
                {
                    foreach ($this->items as $item) {
                        if ($item->id()->equals($id)) {
                            return $item;
                        }
                    }

                    return null;
                }

                public function create(array $attributes): FulfillmentSenderRule
                {
                    throw new BadMethodCallException('create not implemented in test double');
                }

                public function update(Identifier $id, array $attributes): FulfillmentSenderRule
                {
                    throw new BadMethodCallException('update not implemented in test double');
                }

                public function delete(Identifier $id): void
                {
                    throw new BadMethodCallException('delete not implemented in test double');
                }
            }
        );

        $this->app->instance(
            FulfillmentFreightProfileRepository::class,
            new class([$freight]) implements FulfillmentFreightProfileRepository
            {
                public function __construct(private array $items) {}

                public function all(): iterable
                {
                    return $this->items;
                }

                public function paginate(?int $perPage = null, array $filters = []): PaginatedResult
                {
                    $perPage = max(1, $perPage ?? max(1, count($this->items)));
                    $total = count($this->items);
                    $page = 1;
                    $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

                    return PaginatedResult::create(
                        $this->items,
                        $total,
                        $perPage,
                        $page,
                        $lastPage,
                    );
                }

                public function getById(Identifier $shippingProfileId): ?FulfillmentFreightProfile
                {
                    foreach ($this->items as $item) {
                        if ($item->shippingProfileId()->equals($shippingProfileId)) {
                            return $item;
                        }
                    }

                    return null;
                }

                public function create(array $attributes): FulfillmentFreightProfile
                {
                    throw new BadMethodCallException('create not implemented in test double');
                }

                public function update(Identifier $shippingProfileId, array $attributes): FulfillmentFreightProfile
                {
                    throw new BadMethodCallException('update not implemented in test double');
                }

                public function delete(Identifier $shippingProfileId): void
                {
                    throw new BadMethodCallException('delete not implemented in test double');
                }
            }
        );
    }
}
