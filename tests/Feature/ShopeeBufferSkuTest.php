<?php

namespace Tests\Feature;

use App\Http\Controllers\Webhook\ShopeeBufferSkuController;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\LoadSistemConfig;
use App\Services\ProdukCustomService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopeeBufferSkuTest extends TestCase
{
    public function test_route_requires_login(): void
    {
        $this->withoutMiddleware(LoadSistemConfig::class);
        $this->get('/buffer/sku-tidak-ada')->assertRedirect('/login');
    }

    public function test_lists_invalid_and_missing_skus_without_modifying_buffers(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('marketplaces', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace');
            $table->string('shop_id');
            $table->string('nama');
            $table->unsignedBigInteger('cabang_id')->nullable();
        });
        Schema::create('marketplace_buffers', function (Blueprint $table) {
            $table->id();
            $table->string('nota');
            $table->string('shop_id');
            $table->string('mp');
            $table->string('status');
            $table->unsignedBigInteger('project_id')->nullable();
        });
        Schema::create('produks', function (Blueprint $table) {
            $table->id();
        });

        DB::table('marketplaces')->insert(['id' => 1, 'marketplace' => 'shopee', 'shop_id' => '123', 'nama' => 'Toko A']);
        DB::table('produks')->insert(['id' => 10]);
        DB::table('marketplace_buffers')->insert([
            ['nota' => 'A', 'shop_id' => '123', 'mp' => 'shopee', 'status' => 'PROCESSED'],
            ['nota' => 'B', 'shop_id' => '123', 'mp' => 'shopee', 'status' => 'READY_TO_SHIP'],
            ['nota' => 'C', 'shop_id' => '123', 'mp' => 'shopee', 'status' => 'COMPLETED'],
        ]);

        $this->app->instance(ProdukCustomService::class, new class extends ProdukCustomService {
            public function parseSku(string $sku): array
            {
                if (!ctype_digit($sku)) {
                    return parent::parseSku($sku);
                }

                return ['produk_id' => (int) $sku, 'material_ids' => [], 'paket' => 1];
            }
        });
        $this->app->instance(ShopeeBufferSkuController::class, new class extends ShopeeBufferSkuController {
            protected function ambilApi($marketplace, $path, $param = [])
            {
                return ['response' => ['order_list' => [
                    ['order_sn' => 'A', 'item_list' => [
                        ['model_sku' => 'SKU-SALAH'], ['model_sku' => '10'],
                    ]],
                    ['order_sn' => 'B', 'item_list' => [
                        ['model_sku' => '', 'item_sku' => '99'], ['model_sku' => 'NON_PRODUK'],
                    ]],
                ]]];
            }
        });
        $this->withoutMiddleware([Authenticate::class, LoadSistemConfig::class]);

        $this->getJson('/buffer/sku-tidak-ada?marketplace_id=1')
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('total_buffer', 2)
            ->assertJsonPath('total_invalid', 2)
            ->assertJsonPath('invalid.0.nota', 'A')
            ->assertJsonPath('invalid.0.sku', 'SKU-SALAH')
            ->assertJsonPath('invalid.1.nota', 'B')
            ->assertJsonPath('invalid.1.sku', '99');

        $this->assertSame(3, DB::table('marketplace_buffers')->count());
    }
}
