<?php

namespace App\Console\Commands;

use App\Services\DataSopOrganizer;
use Illuminate\Console\Command;

class OrganizeDataSops extends Command
{
    protected $signature = 'sop:rapikan';

    protected $description = 'Rapikan isi SOP dan hapus judul berulang dalam bagian yang sama (dengan cadangan)';

    public function handle(DataSopOrganizer $organizer): int
    {
        $hasil = $organizer->run();
        $this->info("{$hasil['hapus']} duplikat dihapus; {$hasil['dirapikan']} isi SOP dirapikan.");
        $this->line('Cadangan: ' . $hasil['backup']);

        return self::SUCCESS;
    }
}
