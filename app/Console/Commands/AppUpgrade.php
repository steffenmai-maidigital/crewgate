<?php

namespace App\Console\Commands;

use App\Services\UpgradeService;
use Illuminate\Console\Command;

class AppUpgrade extends Command
{
    protected $signature = 'app:upgrade';

    protected $description = 'Sicheres Update: Versionsabgleich, Backup, Migrationen, Konvertierungen';

    public function handle(UpgradeService $upgrade): int
    {
        try {
            $upgrade->run();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('System ist aktuell: Version ' . config('version.number') . ', Build ' . config('version.build') . '.');

        return self::SUCCESS;
    }
}
