<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PenggajianController;
use App\Models\Member;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PenggajianKehadiranTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('jenis');
            $table->date('tgl_masuk');
            $table->string('tgl_gajian')->nullable();
            $table->softDeletes();
        });
        Schema::create('gajis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('level_id');
            $table->unsignedBigInteger('bagian_id');
            $table->integer('transportasi');
            $table->integer('performance');
            $table->softDeletes();
        });
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->integer('kehadiran')->nullable();
            $table->integer('gaji_pokok');
            $table->integer('transportasi');
            $table->integer('lama_kerja');
            $table->integer('harga_lembur');
            $table->softDeletes();
        });
        Schema::create('bagians', function (Blueprint $table) {
            $table->id();
            $table->integer('grade');
            $table->softDeletes();
        });
        Schema::create('lemburs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->string('dibayar');
            $table->string('status');
            $table->integer('jam');
            $table->softDeletes();
        });
        Schema::create('kasbons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->integer('saldo');
            $table->softDeletes();
        });
        Schema::create('absensis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->date('tanggal');
            $table->string('jenis');
            $table->softDeletes();
        });
        Schema::create('akun_details', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->softDeletes();
        });

        DB::table('members')->insert(['id' => 56, 'jenis' => 'karyawan', 'tgl_masuk' => '2025-01-01']);
        DB::table('bagians')->insert(['id' => 1, 'grade' => 1]);
        DB::table('levels')->insert([
            'id' => 4, 'kehadiran' => 0, 'gaji_pokok' => 1000000,
            'transportasi' => 0, 'lama_kerja' => 0, 'harga_lembur' => 0,
        ]);
        DB::table('gajis')->insert([
            'member_id' => 56, 'level_id' => 4, 'bagian_id' => 1,
            'transportasi' => 0, 'performance' => 0,
        ]);
    }

    public function test_zero_level_kehadiran_does_not_create_an_allowance(): void
    {
        $form = app(PenggajianController::class)->create(Member::findOrFail(56));

        $this->assertSame(0, $form->getData()['tunjanganKehadiran']);
    }

    public function test_null_level_kehadiran_keeps_existing_default(): void
    {
        DB::table('levels')->where('id', 4)->update(['kehadiran' => null]);

        $form = app(PenggajianController::class)->create(Member::findOrFail(56));

        $this->assertSame(100000, $form->getData()['tunjanganKehadiran']);
    }
}
