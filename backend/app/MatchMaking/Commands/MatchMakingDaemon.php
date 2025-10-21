<?php

declare(strict_types=1);

namespace App\MatchMaking\Commands;

use App\MatchMaking\MatchMakingManager;
use Illuminate\Console\Command;

class MatchMakingDaemon extends Command
{
    protected $signature = 'app:match-making:daemon {--hz=5}';

    protected $description = 'Run matchmaking loop';

    public function handle(MatchMakingManager $matchMakingManager): int
    {
        $hz = (int) $this->option('hz');
        $sleepMicros = max(1, (int) (1_000_000 / $hz));

        $this->info("Matchmaking daemon @ {$hz} Hz");
        while (true) {
            $matchMakingManager->processTick();
            usleep($sleepMicros);
        }

        return self::SUCCESS;
    }
}
