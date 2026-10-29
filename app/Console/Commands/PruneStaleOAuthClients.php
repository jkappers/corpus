<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Passport\Client;

#[Signature('mcp:prune-clients {--days=7 : Minimum age of a client before it can be pruned}')]
#[Description('Delete dynamically registered OAuth clients that never completed a sign-in')]
class PruneStaleOAuthClients extends Command
{
    public function handle(): int
    {
        $count = Client::query()
            ->where('created_at', '<', now()->subDays((int) $this->option('days')))
            ->whereDoesntHave('tokens')
            ->whereDoesntHave('authCodes')
            ->delete();

        $this->components->info("Pruned {$count} stale OAuth clients.");

        return self::SUCCESS;
    }
}
