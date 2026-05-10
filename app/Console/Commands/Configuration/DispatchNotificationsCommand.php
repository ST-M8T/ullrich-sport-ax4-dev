<?php

namespace App\Console\Commands\Configuration;

use App\Application\Configuration\NotificationDispatchService;
use App\Domain\Configuration\NotificationMessage;
use Illuminate\Console\Command;

class DispatchNotificationsCommand extends Command
{
    protected $signature = 'notifications:dispatch {--limit=50}';

    protected $description = 'Send pending notification messages';

    public function __construct(private readonly NotificationDispatchService $dispatcher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit') ?: 50;
        $this->components->info(sprintf('Sende bis zu %d Benachrichtigungen ...', $limit));

        $processed = 0;
        $sent = 0;

        $this->dispatcher->dispatchPending($limit, function (NotificationMessage $message, bool $success) use (&$processed, &$sent): void {
            $processed++;
            if ($success) {
                $sent++;
                $this->line(sprintf(
                    ' [%d] %s → gesendet',
                    $message->id()->toInt(),
                    $message->notificationType()
                ));

                return;
            }

            $this->warn(sprintf(
                ' [%d] %s → übersprungen (fehlende Daten oder Template)',
                $message->id()->toInt(),
                $message->notificationType()
            ));
        });

        $this->components->success(sprintf('%d Benachrichtigungen verarbeitet, %d gesendet.', $processed, $sent));

        return self::SUCCESS;
    }
}
