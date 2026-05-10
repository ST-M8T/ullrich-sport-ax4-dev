<?php

declare(strict_types=1);

namespace App\Console\Commands\Dispatch;

use App\Application\Dispatch\DispatchListService;
use App\Domain\Shared\ValueObjects\Identifier;
use Illuminate\Console\Command;

class CloseDispatchListCommand extends Command
{
    protected $signature = 'dispatch:close
        {list : Dispatch list ID}
        {--user-id= : User closing the list}
        {--export-file= : Optional export filename}';

    protected $description = 'Close a dispatch list, optionally exporting it';

    public function __construct(private readonly DispatchListService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $listId = Identifier::fromInt((int) $this->argument('list'));
        $userIdOption = $this->option('user-id');
        $exportFile = $this->option('export-file');

        if (! is_string($userIdOption) || $userIdOption === '') {
            $this->error('user-id option is required.');

            return self::FAILURE;
        }

        $userId = Identifier::fromInt((int) $userIdOption);

        $exportFilename = is_string($exportFile) && $exportFile !== '' ? $exportFile : null;

        try {
            $list = $this->service->closeList($listId, $userId, $exportFilename);

            if ($exportFilename !== null) {
                $list = $this->service->exportList($listId, $userId, $exportFilename);
                $this->info(sprintf(
                    'Dispatch list #%d closed and exported as %s.',
                    $list->id()->toInt(),
                    $exportFilename
                ));

                return self::SUCCESS;
            }

            $this->info(sprintf('Dispatch list #%d closed.', $list->id()->toInt()));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
