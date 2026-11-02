<?php

namespace App\Console\Commands;

use App\Services\Platform\Subscriptions\StatusRefresher;
use Illuminate\Console\Command;

/** Daily (01:00 school time, see SubscriptionServiceProvider): report subscription state changes (FR-806, FR-808). */
class RefreshSubscriptions extends Command
{
    protected $signature = 'erp:subscriptions:refresh';

    protected $description = 'Recompute every school subscription state, log transitions and audit schools entering grace or expiry';

    public function handle(StatusRefresher $refresher): int
    {
        $result = $refresher->refresh();

        foreach ($result['changed'] as $change) {
            $this->line(sprintf('School #%d: %s -> %s', $change['school_id'], $change['from'] ?? 'new', $change['to']));
        }

        $this->info(sprintf('Checked %d subscriptions, %d changed state.', $result['checked'], count($result['changed'])));

        return self::SUCCESS;
    }
}
