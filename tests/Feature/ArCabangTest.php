<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ArController;
use App\Models\Ar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ArCabangTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('cabangs', function (Blueprint $table) {
            $table->id();
            $table->string('kode');
            $table->string('nama');
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
            $table->string('kode')->nullable();
            $table->string('warna')->nullable();
            $table->string('ttd')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        DB::table('cabangs')->insert([
            ['id' => 1, 'kode' => 'PUSAT', 'nama' => 'Pusat', 'status' => 1],
            ['id' => 2, 'kode' => 'JKT', 'nama' => 'Jakarta', 'status' => 1],
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

        Gate::shouldReceive('denies')->andReturn(false);
    }

    public function test_daftar_ar_jakarta_hanya_memuat_member_jakarta(): void
    {
        session(['cabang_id' => 2]);

        $ars = app(ArController::class)->index()->getData()['ars'];

        $this->assertSame([2], $ars->pluck('id')->all());
        $this->assertSame('CS Jakarta', $ars->first()->member->nama_lengkap);
    }

    public function test_daftar_ar_semua_cabang_tidak_memuat_member_yang_hilang(): void
    {
        session(['cabang_id' => 'all']);

        $ars = app(ArController::class)->index()->getData()['ars'];

        $this->assertSame([1, 2], $ars->pluck('id')->all());
    }

    public function test_form_buat_menyediakan_cabang_dan_member_lintas_cabang_yang_belum_punya_ar(): void
    {
        session(['cabang_id' => 1]);
        DB::table('members')->insert(['id' => 4, 'cabang_id' => 2, 'nama_lengkap' => 'CS Baru Jakarta']);

        $data = app(ArController::class)->create()->getData();

        $this->assertSame([2, 1], $data['cabangs']->pluck('id')->all());
        $this->assertSame([4], $data['members']->pluck('id')->all());
    }

    public function test_form_edit_memuat_member_ar_meski_cabang_aktif_berbeda(): void
    {
        session(['cabang_id' => 1]);

        $data = app(ArController::class)->edit(Ar::findOrFail(2))->getData();

        $this->assertSame(2, $data['ar']->member->cabang_id);
        $this->assertSame([2], $data['members']->pluck('id')->all());
    }

    public function test_edit_dapat_memindahkan_ar_ke_member_cabang_lain_tanpa_mengganti_ttd(): void
    {
        session(['cabang_id' => 1]);
        DB::table('ars')->where('id', 1)->update(['ttd' => 'lama.png']);
        DB::table('members')->insert(['id' => 4, 'cabang_id' => 2, 'nama_lengkap' => 'CS Baru Jakarta']);

        app(ArController::class)->update(Request::create('/admin/ars/1', 'PATCH', [
            'cabang_id' => 2,
            'member_id' => 4,
            'kode' => 'JKT',
            'warna' => '#ffffff',
        ]), Ar::findOrFail(1));

        $this->assertSame(4, Ar::findOrFail(1)->member_id);
        $this->assertSame('lama.png', Ar::findOrFail(1)->ttd);
    }

    public function test_edit_menolak_member_dari_cabang_yang_tidak_dipilih(): void
    {
        $this->expectException(ValidationException::class);

        app(ArController::class)->update(Request::create('/admin/ars/1', 'PATCH', [
            'cabang_id' => 2,
            'member_id' => 1,
            'kode' => 'JKT',
            'warna' => '#ffffff',
        ]), Ar::findOrFail(1));
    }
}
