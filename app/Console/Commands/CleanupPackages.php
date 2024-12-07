<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Package;

class CleanupPackages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired packages and their files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Najdeme všechny expirované záznamy
        $expiredPackages = Package::all()->filter(function ($package) {
            return $package->isExpired();
        });

        foreach ($expiredPackages as $package) {
            // Smažeme soubor
            Storage::delete('zips/'.$package->filename);
            // Smažeme záznam z DB
            $package->delete();
        }

        $this->info('Expired packages cleaned up.');
        $this->info('Deleted packages: '.$expiredPackages->count());
        $this->info('Time of cleanup: '.now());
        return 0;
    }
}
