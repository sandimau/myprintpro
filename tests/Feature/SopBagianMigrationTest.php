<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SopBagianMigrationTest extends TestCase
{
    public function test_bagian_lama_dipindahkan_ke_tabel_bagian_tanpa_menghilangkan_sop(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/2026_11_25_000001_create_data_sops_table.php'))->up();
        DB::table('data_sops')->insert([
            ['id' => 10, 'judul' => 'Umum', 'isi' => 'a', 'bagian' => 'depan'],
            ['id' => 11, 'judul' => 'Harga', 'isi' => 'b', 'bagian' => 'pricelist'],
            ['id' => 12, 'judul' => 'Lain', 'isi' => 'c', 'bagian' => 'Kustom'],
            ['id' => 13, 'judul' => 'Kosong', 'isi' => 'd', 'bagian' => null],
        ]);

        (require database_path('migrations/2026_11_25_000003_create_sop_bagians_table.php'))->up();

        $this->assertSame(4, DB::table('data_sops')->count());
        $this->assertFalse(Schema::hasColumn('data_sops', 'bagian'));
        $this->assertSame('Umum', DB::table('data_sops')->join('sop_bagians', 'sop_bagians.id', '=', 'data_sops.sop_bagian_id')->where('data_sops.id', 10)->value('sop_bagians.nama'));
        $this->assertSame('Daftar Harga', DB::table('data_sops')->join('sop_bagians', 'sop_bagians.id', '=', 'data_sops.sop_bagian_id')->where('data_sops.id', 11)->value('sop_bagians.nama'));
        $this->assertSame('Kustom', DB::table('data_sops')->join('sop_bagians', 'sop_bagians.id', '=', 'data_sops.sop_bagian_id')->where('data_sops.id', 12)->value('sop_bagians.nama'));
        $this->assertSame('Umum', DB::table('data_sops')->join('sop_bagians', 'sop_bagians.id', '=', 'data_sops.sop_bagian_id')->where('data_sops.id', 13)->value('sop_bagians.nama'));
    }
}
