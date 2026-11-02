<?php

namespace App\Console\Commands;

use App\Models\Platform\School;
use App\Services\Identity\RoleService;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'erp:sync-permissions';

    protected $description = 'Grant default permissions added by newer modules to every school\'s built-in roles (never removes anything)';

    public function handle(RoleService $roles): int
    {
        $total = 0;

        foreach (School::query()->orderBy('id')->get() as $school) {
            $added = $roles->grantMissingDefaults($school);
            $total += $added;

            if ($added > 0) {
                $this->line("{$school->name}: +{$added}");
            }
        }

        $this->info("Done. {$total} permission grants added.");

        return self::SUCCESS;
    }
}
