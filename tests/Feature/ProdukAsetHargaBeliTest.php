<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ProdukController;
use App\Models\Kategori;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProdukAsetHargaBeliTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        session()->put('cabang_id', 1);

        Schema::create('produk_kategori_utamas', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
        });
        Schema::create('produk_kategoris', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kategori_utama_id');
            $table->string('nama');
        });
        Schema::create('produk_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kategori_id');
            $table->string('nama');
            $table->integer('harga');
        });
        Schema::create('produks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produk_model_id');
            $table->string('nama');
            $table->integer('hpp');
        });
        Schema::create('produk_last_stoks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produk_id');
            $table->unsignedBigInteger('cabang_id');
            $table->integer('saldo');
            $table->integer('tahun');
        });
        Schema::create('belanja_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produk_id');
            $table->integer('harga');
            $table->softDeletes();
        });

        DB::table('produk_kategori_utamas')->insert(['id' => 1, 'nama' => 'Utama']);
        DB::table('produk_kategoris')->insert(['id' => 1, 'kategori_utama_id' => 1, 'nama' => 'Kategori']);
        DB::table('produk_models')->insert(['id' => 1, 'kategori_id' => 1, 'nama' => 'Model', 'harga' => 1000]);
        DB::table('produks')->insert([
            ['id' => 1, 'produk_model_id' => 1, 'nama' => 'A', 'hpp' => 500],
            ['id' => 2, 'produk_model_id' => 1, 'nama' => 'B', 'hpp' => 600],
        ]);
        DB::table('produk_last_stoks')->insert([
            ['produk_id' => 1, 'cabang_id' => 1, 'saldo' => 3, 'tahun' => 2026],
            ['produk_id' => 2, 'cabang_id' => 1, 'saldo' => 2, 'tahun' => 2026],
        ]);
        DB::table('belanja_details')->insert([
            ['produk_id' => 1, 'harga' => 100, 'deleted_at' => null],
            ['produk_id' => 1, 'harga' => 200, 'deleted_at' => null],
            ['produk_id' => 1, 'harga' => 900, 'deleted_at' => now()],
        ]);
    }

    public function test_aset_menggunakan_harga_beli_terakhir_yang_tidak_dihapus(): void
    {
        $controller = app(ProdukController::class);
        $ringkasan = $controller->aset()->getData()['asets'];

        $this->assertCount(1, $ringkasan);
        $this->assertEquals(600, $ringkasan->first()->nilai_aset);

        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$parts) => implode('', $parts));
        $detail = $controller->asetDetail(Kategori::findOrFail(1))->getData()['asets'];

        $this->assertEquals(600, $detail->firstWhere('varian', 'A')->nilai_aset);
        $this->assertEquals(0, $detail->firstWhere('varian', 'B')->nilai_aset);
    }
}
