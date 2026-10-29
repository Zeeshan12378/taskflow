<?php

namespace App\Console\Commands;

use App\Models\Cycle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cycles:snapshot-active')]
#[Description('Record daily progress snapshots for active team sprints')]
class SnapshotActiveCycles extends Command
{
    public function handle(): int
    {
        $snapshotDate = today();
        $cyclesSnapshotted = 0;

        Cycle::query()
            ->where('status', 'active')
            ->chunkById(100, function ($cycles) use ($snapshotDate, &$cyclesSnapshotted): void {
                foreach ($cycles as $cycle) {
                    $cycle->recordSnapshot($snapshotDate, 'daily');
                    $cyclesSnapshotted++;
                }
            });

        $this->info("Recorded progress for {$cyclesSnapshotted} active sprints.");

        return self::SUCCESS;
    }
}
