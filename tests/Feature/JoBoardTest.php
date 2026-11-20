<?php

namespace Tests\Feature;

use App\Services\JoBoard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JoBoardTest extends TestCase
{
    public function test_detail_jo_menyediakan_tombol_cetak_dan_script_di_stack_layout(): void
    {
        $compiled = app('blade.compiler')->compileString(file_get_contents(
            resource_path('views/admin/jo/detail.blade.php')
        ));

        $this->assertStringContainsString('cetakJo(', $compiled);
        $this->assertStringContainsString('cetakSemuaJo()', $compiled);
        $this->assertStringContainsString("startPush('after-scripts')", $compiled);
    }

    public function test_papan_menggabungkan_offline_dan_online_tetapi_mengabaikan_buffer_selesai(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('jo_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produksi_id');
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cabang_id')->nullable();
            $table->unsignedBigInteger('kontak_id')->nullable();
            $table->string('nota');
            $table->integer('total');
            $table->date('deathline')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('produksi_id');
            $table->unsignedBigInteger('produk_id')->nullable();
            $table->unsignedBigInteger('proses_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('project_mps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cabang_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('marketplace_id')->nullable();
            $table->string('nota');
            $table->string('konsumen')->nullable();
            $table->integer('total');
            $table->date('deadline')->nullable();
            $table->timestamps();
        });
        Schema::create('project_mp_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('produksi_id');
            $table->unsignedBigInteger('produk_id')->nullable();
            $table->timestamps();
        });
        Schema::create('marketplace_buffers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('status');
            $table->boolean('custom');
            $table->boolean('ada_proses')->default(false);
        });
        Schema::create('produksis', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->softDeletes();
        });
        Schema::create('cabangs', function (Blueprint $table) {
            $table->id();
            $table->string('kode');
        });

        DB::table('jo_statuses')->insert(['produksi_id' => 10]);
        DB::table('produksis')->insert(['id' => 10, 'nama' => 'Cetak']);
        DB::table('cabangs')->insert([
            ['id' => 1, 'kode' => 'PUSAT'],
            ['id' => 2, 'kode' => 'CABANG-2'],
            ['id' => 3, 'kode' => 'CABANG-3'],
        ]);
        DB::table('orders')->insert([
            ['id' => 1, 'nota' => 'OFF', 'total' => 1000, 'cabang_id' => null],
            ['id' => 2, 'nota' => 'LINKED', 'total' => 2000, 'cabang_id' => null],
            ['id' => 3, 'nota' => 'OFF-2', 'total' => 1000, 'cabang_id' => 2],
            ['id' => 4, 'nota' => 'OFF-3', 'total' => 1000, 'cabang_id' => 3],
        ]);
        DB::table('order_details')->insert([
            ['order_id' => 1, 'produksi_id' => 10],
            ['order_id' => 2, 'produksi_id' => 10],
            ['order_id' => 3, 'produksi_id' => 10],
            ['order_id' => 4, 'produksi_id' => 10],
        ]);
        DB::table('project_mps')->insert([
            ['id' => 11, 'order_id' => 2, 'nota' => 'AKTIF', 'total' => 2000, 'cabang_id' => 2],
            ['id' => 12, 'order_id' => null, 'nota' => 'SELESAI', 'total' => 3000, 'cabang_id' => null],
            ['id' => 13, 'order_id' => null, 'nota' => 'ONLINE-3', 'total' => 3000, 'cabang_id' => 3],
        ]);
        DB::table('project_mp_details')->insert([
            ['project_id' => 11, 'produksi_id' => 10],
            ['project_id' => 12, 'produksi_id' => 10],
            ['project_id' => 13, 'produksi_id' => 10],
        ]);
        DB::table('marketplace_buffers')->insert([
            ['project_id' => 11, 'status' => 'PROCESSED', 'custom' => 1],
            ['project_id' => 12, 'status' => 'PROCESSED', 'custom' => 1],
            ['project_id' => 12, 'status' => 'COMPLETED', 'custom' => 1],
            ['project_id' => 13, 'status' => 'PROCESSED', 'custom' => 1],
        ]);

        $board = app(JoBoard::class);
        $this->assertSame([10], $board->statusIds());
        session(['cabang_id' => 'all']);
        $this->assertEqualsCanonicalizing(['OFF', 'OFF-2', 'OFF-3', 'AKTIF', 'ONLINE-3'], $board->orders($board->statusIds())->pluck('nota')->all());

        session(['cabang_id' => 1]);
        $this->assertSame(['OFF'], $board->orders($board->statusIds())->pluck('nota')->all());
        session(['cabang_id' => 2]);
        $this->assertEqualsCanonicalizing(['OFF-2', 'AKTIF'], $board->orders($board->statusIds())->pluck('nota')->all());
        session(['cabang_id' => 3]);
        $this->assertEqualsCanonicalizing(['OFF-3', 'ONLINE-3'], $board->orders($board->statusIds())->pluck('nota')->all());
        $this->assertTrue($board->orders([])->isEmpty());
    }
}
