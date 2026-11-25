<?php

namespace Tests\Feature;

use App\Services\PengingatGajian;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PengingatGajianTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap');
            $table->string('tgl_gajian')->nullable();
            $table->string('status');
            $table->softDeletes();
        });
    }

    public function test_menampilkan_anggota_aktif_yang_gajian_hari_ini_tanpa_penanda_whattodo(): void
    {
        DB::table('members')->insert([
            ['id' => 1, 'nama_lengkap' => 'Ani', 'tgl_gajian' => '25', 'status' => '1', 'deleted_at' => null],
            ['id' => 2, 'nama_lengkap' => 'Budi', 'tgl_gajian' => '2024-01-25', 'status' => '1', 'deleted_at' => null],
            ['id' => 3, 'nama_lengkap' => 'Cici', 'tgl_gajian' => '26', 'status' => '1', 'deleted_at' => null],
            ['id' => 4, 'nama_lengkap' => 'Dedi', 'tgl_gajian' => '25', 'status' => '0', 'deleted_at' => null],
            ['id' => 5, 'nama_lengkap' => 'Eka', 'tgl_gajian' => '25', 'status' => '1', 'deleted_at' => '2026-01-01'],
        ]);

        $pengingat = app(PengingatGajian::class);

        $this->assertSame(['Ani', 'Budi'], $pengingat->hariIni(Carbon::parse('2026-11-25'))->pluck('nama_lengkap')->all());
        $this->assertSame(['Ani', 'Budi'], $pengingat->hariIni(Carbon::parse('2026-12-25'))->pluck('nama_lengkap')->all());
    }

    public function test_tanggal_gajian_di_akhir_bulan_mengikuti_hari_terakhir_bulan(): void
    {
        DB::table('members')->insert([
            ['nama_lengkap' => 'Februari', 'tgl_gajian' => '29', 'status' => '1'],
            ['nama_lengkap' => 'Akhir Bulan', 'tgl_gajian' => '31', 'status' => '1'],
        ]);

        $pengingat = app(PengingatGajian::class);

        $this->assertCount(2, $pengingat->hariIni(Carbon::parse('2026-02-28')));
        $this->assertSame(['Akhir Bulan'], $pengingat->hariIni(Carbon::parse('2026-04-30'))->pluck('nama_lengkap')->all());
        $this->assertCount(0, $pengingat->hariIni(Carbon::parse('2026-03-28')));
    }

    public function test_tampilan_dashboard_dapat_dikompilasi(): void
    {
        $template = file_get_contents(resource_path('views/admin/dashboard/index.blade.php'));
        $compiled = Blade::compileString($template);

        $this->assertStringContainsString('chart-offline', $compiled);
        $this->assertStringContainsString('chart-online', $compiled);
        $this->assertStringContainsString('members.penggajian', $compiled);
    }
}
