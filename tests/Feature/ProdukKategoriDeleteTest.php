<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ProdukKategoriController;
use App\Models\ProdukKategori;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProdukKategoriDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('produk_kategoris', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->unsignedBigInteger('kategori_utama_id');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('produk_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kategori_id');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('produks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produk_model_id');
        });

        DB::table('produk_kategoris')->insert([
            ['id' => 1, 'nama' => 'Target', 'kategori_utama_id' => 15],
            ['id' => 2, 'nama' => 'Lain', 'kategori_utama_id' => 15],
        ]);
        DB::table('produk_models')->insert([
            ['id' => 1, 'kategori_id' => 1],
            ['id' => 2, 'kategori_id' => 1],
            ['id' => 3, 'kategori_id' => 2],
        ]);
        DB::table('produks')->insert([
            ['id' => 1, 'produk_model_id' => 1],
            ['id' => 2, 'produk_model_id' => 2],
            ['id' => 3, 'produk_model_id' => 3],
        ]);
    }

    private function loginAs(string $role): void
    {
        $user = new User();
        $user->id = 1;
        $user->setRelation('roles', collect([(object) ['name' => $role]]));
        $this->actingAs($user);
    }

    public function test_hanya_super_bisa_menghapus_kategori_dan_semua_produk_di_dalamnya(): void
    {
        $this->loginAs('manager');
        try {
            app(ProdukKategoriController::class)->destroy(ProdukKategori::findOrFail(1));
            $this->fail('Role selain super seharusnya ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('produks', 3);

        $this->loginAs('super');
        $response = app(ProdukKategoriController::class)->destroy(ProdukKategori::findOrFail(1));

        $this->assertSame(route('produk-kategori.index', ['kategori_utama_id' => 15]), $response->getTargetUrl());
        $this->assertSoftDeleted('produk_kategoris', ['id' => 1]);
        $this->assertSoftDeleted('produk_models', ['id' => 1]);
        $this->assertSoftDeleted('produk_models', ['id' => 2]);
        $this->assertDatabaseMissing('produks', ['id' => 1]);
        $this->assertDatabaseMissing('produks', ['id' => 2]);
        $this->assertDatabaseHas('produks', ['id' => 3]);
        $this->assertDatabaseHas('produk_kategoris', ['id' => 2, 'deleted_at' => null]);
    }
}
