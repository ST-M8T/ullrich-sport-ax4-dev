<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Configuration;

use App\Application\Configuration\Channels\NotificationChannel;
use App\Application\Configuration\NotificationDispatchService;
use App\Domain\Configuration\Contracts\NotificationRepository;
use App\Domain\Configuration\NotificationMessage;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class NotificationDispatchServiceTest extends TestCase
{
    public function test_dispatch_pending_uses_mail_channel_by_default(): void
    {
        Event::fake();

        $message = $this->createMessage(null);
        $repository = new InMemoryNotificationRepository($message);
        $channel = new TestNotificationChannel('mail');

        $service = new NotificationDispatchService($repository, [$channel]);
        $dispatched = $service->dispatchPending(10);

        $this->assertSame(1, $dispatched);
        $this->assertCount(1, $channel->sentMessages);
        $this->assertSame('sent', $repository->lastSaved?->status());
    }

    public function test_dispatch_pending_routes_to_specific_channel(): void
    {
        Event::fake();

        $message = $this->createMessage('slack');
        $repository = new InMemoryNotificationRepository($message);
        $mailChannel = new TestNotificationChannel('mail', false);
        $slackChannel = new TestNotificationChannel('slack');

        $service = new NotificationDispatchService($repository, [$mailChannel, $slackChannel]);
        $count = $service->dispatchPending();

        $this->assertSame(1, $count);
        $this->assertCount(1, $slackChannel->sentMessages);
        $this->assertCount(0, $mailChannel->sentMessages);
        $this->assertSame('sent', $repository->lastSaved?->status());
    }

    public function test_dispatch_skips_disabled_channel(): void
    {
        Event::fake();

        $message = $this->createMessage(null);
        $repository = new InMemoryNotificationRepository($message);
        $channel = new TestNotificationChannel('mail', false);

        $service = new NotificationDispatchService($repository, [$channel]);
        $count = $service->dispatchPending();

        $this->assertSame(0, $count);
        $this->assertNull($repository->lastSaved);
        $this->assertCount(0, $channel->sentMessages);
    }

    public function test_dispatch_stops_when_channel_send_fails(): void
    {
        Event::fake();

        $message = $this->createMessage('sms');
        $repository = new InMemoryNotificationRepository($message);
        $channel = new TestNotificationChannel('sms', true, false);

        $service = new NotificationDispatchService($repository, [$channel]);
        $count = $service->dispatchPending();

        $this->assertSame(0, $count);
        $this->assertNull($repository->lastSaved);
        $this->assertCount(1, $channel->sentMessages);
    }

    private function createMessage(?string $channel): NotificationMessage
    {
        return NotificationMessage::hydrate(
            Identifier::fromInt(1),
            'test.notification',
            $channel,
            ['template' => 'test', 'to' => 'recipient@example.test', 'text' => 'test'],
            'pending',
            null,
            null,
            null,
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('-1 hour'),
        );
    }
}

final class TestNotificationChannel implements NotificationChannel
{
    /** @var list<NotificationMessage> */
    public array $sentMessages = [];

    public function __construct(
        private readonly string $key,
        private bool $enabled = true,
        private bool $sendResult = true,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function send(NotificationMessage $message): bool
    {
        $this->sentMessages[] = $message;

        return $this->sendResult;
    }
}

final class InMemoryNotificationRepository implements NotificationRepository
{
    /** @var array<int,NotificationMessage> */
    private array $messages = [];

    public ?NotificationMessage $lastSaved = null;

    public function __construct(NotificationMessage ...$messages)
    {
        foreach ($messages as $message) {
            $this->messages[$message->id()->toInt()] = $message;
        }
    }

    public function nextIdentity(): Identifier
    {
        $max = empty($this->messages) ? 0 : max(array_keys($this->messages));

        return Identifier::fromInt($max + 1);
    }

    public function save(NotificationMessage $notification): void
    {
        $this->messages[$notification->id()->toInt()] = $notification;
        $this->lastSaved = $notification;
    }

    public function getById(Identifier $id): ?NotificationMessage
    {
        return $this->messages[$id->toInt()] ?? null;
    }

    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable
    {
        return array_slice(array_values($this->messages), $offset, $limit);
    }
}
