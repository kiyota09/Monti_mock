<?php

namespace App\Console\Commands;

use App\Services\Crm\ImapInboxFetcher;
use Illuminate\Console\Command;

class SyncCrmEmail extends Command
{
    protected $signature = 'crm:sync-email';

    protected $description = 'Import unseen client emails from the configured IMAP mailbox into the CRM Email inbox.';

    public function handle(ImapInboxFetcher $fetcher): int
    {
        $result = $fetcher->fetch();

        if ($result['error']) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->info("Imported {$result['imported']} new email(s); skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
