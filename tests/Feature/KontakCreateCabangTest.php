<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\KontakController;
use App\Models\Kontak;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KontakCreateCabangTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('cabangs', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode');
            $table->boolean('status');
        });
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cabang_id');
            $table->string('nama_lengkap');
            $table->softDeletes();
        });
        Schema::create('ars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->softDeletes();
        });
        Schema::create('kontaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ar_id')->nullable();
            $table->unsignedBigInteger('cabang_id');
            $table->string('nama');
            $table->softDeletes();
        });

        DB::table('cabangs')->insert([
            ['id' => 1, 'nama' => 'Pusat', 'kode' => 'PUSAT', 'status' => 1],
            ['id' => 2, 'nama' => 'Jakarta', 'kode' => 'JKT', 'status' => 1],
        ]);
        DB::table('members')->insert([
            ['id' => 1, 'cabang_id' => 1, 'nama_lengkap' => 'CS Pusat'],
            ['id' => 2, 'cabang_id' => 2, 'nama_lengkap' => 'CS Jakarta'],
        ]);
        DB::table('ars')->insert([
            ['id' => 1, 'member_id' => 1],
            ['id' => 2, 'member_id' => 2],
            ['id' => 3, 'member_id' => 999],
        ]);
    }

    public function test_form_kontak_jakarta_hanya_memuat_cs_yang_tersedia_di_jakarta(): void
    {
        session(['cabang_id' => 2]);

        $view = app(KontakController::class)->create();

        $this->assertSame([2], $view->getData()['ars']->pluck('id')->all());
        $this->assertSame('CS Jakarta', $view->getData()['ars']->first()->member->nama_lengkap);
    }

    public function test_detail_kontak_jakarta_menampilkan_cs_terkait_meski_member_di_cabang_lain(): void
    {
        session(['cabang_id' => 2]);
        DB::table('kontaks')->insert(['id' => 1216, 'ar_id' => 1, 'cabang_id' => 2, 'nama' => 'Kontak Jakarta']);

        $view = app(KontakController::class)->show(Kontak::findOrFail(1216));

        $this->assertSame('CS Pusat', $view->getData()['kontak']->ar->member->nama_lengkap);
    }

    public function test_detail_kontak_tetap_bisa_dibuka_jika_member_ar_tidak_ada(): void
    {
        session(['cabang_id' => 2]);
        DB::table('kontaks')->insert(['id' => 1216, 'ar_id' => 3, 'cabang_id' => 2, 'nama' => 'Kontak Jakarta']);

        $view = app(KontakController::class)->show(Kontak::findOrFail(1216));

        $this->assertNull($view->getData()['kontak']->ar->member);
    }
}
