<?php

namespace Tests\Feature;

use App\Http\Controllers\Webhook\BufferController;
use App\Models\Marketplace;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class BufferExistingProjectAcrossCabangTest extends TestCase
{
    public function test_invalid_sku_does_not_stop_next_order_in_same_batch(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('marketplaces', function (Blueprint $table) {
            $table->id();
            $table->string('shop_id');
            $table->unsignedBigInteger('cabang_id');
        });
        Schema::create('project_mps', function (Blueprint $table) {
            $table->id();
            $table->string('nota')->unique();
            $table->unsignedBigInteger('marketplace_id');
            $table->unsignedBigInteger('cabang_id');
            $table->integer('total')->nullable();
            $table->string('konsumen')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('shipping')->nullable();
            $table->dateTime('deadline')->nullable();
            $table->timestamps();
        });
        Schema::create('marketplace_buffers', function (Blueprint $table) {
            $table->id();
            $table->string('nota');
            $table->string('mp');
            $table->string('status');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('marketplace_id')->nullable();
            $table->timestamps();
        });
        Schema::create('marketplace_logs', function (Blueprint $table) {
            $table->id();
            $table->string('isi');
            $table->string('jenis');
            $table->string('shop_id');
            $table->string('marketplace')->nullable();
            $table->dateTime('tanggal');
            $table->timestamps();
        });

        DB::table('marketplaces')->insert(['id' => 10, 'shop_id' => '123', 'cabang_id' => 2]);
        DB::table('project_mps')->insert(['id' => 20, 'nota' => 'BAIK', 'marketplace_id' => 10, 'cabang_id' => 2]);
        DB::table('marketplace_buffers')->insert([
            ['nota' => 'RUSAK', 'mp' => 'shopee', 'status' => 'PROCESSED'],
            ['nota' => 'BAIK', 'mp' => 'shopee', 'status' => 'PROCESSED'],
        ]);

        $controller = new class extends BufferController {
            protected function ambilApi($marketplace, $path, $param = [])
            {
                return ['response' => ['order_list' => [
                    [
                        'order_sn' => 'RUSAK', 'message_to_seller' => '', 'create_time' => time(),
                        'total_amount' => 100, 'buyer_username' => 'Pembeli', 'shipping_carrier' => 'Kurir',
                        'item_list' => [['model_sku' => 'SKU-SALAH', 'item_sku' => 'SKU-SALAH']],
                    ],
                    ['order_sn' => 'BAIK', 'message_to_seller' => ''],
                ]]];
            }
        };

        $method = new ReflectionMethod(BufferController::class, 'prosesBufferShop');
        $errors = with_cabang(2, fn () => $method->invoke(
            $controller, Marketplace::withoutGlobalScope('cabang')->find(10), DB::table('marketplace_buffers')->get()
        ));

        $this->assertCount(1, $errors);
        $this->assertSame('RUSAK', $errors[0]['nota']);
        $this->assertStringContainsString('Pesanan Shopee RUSAK:', $errors[0]['message']);
        $this->assertNull(DB::table('marketplace_buffers')->where('nota', 'RUSAK')->value('project_id'));
        $this->assertSame(20, DB::table('marketplace_buffers')->where('nota', 'BAIK')->value('project_id'));
    }

    public function test_invalid_shopee_sku_error_includes_order_number(): void
    {
        $method = new ReflectionMethod(BufferController::class, 'parseShopeeSku');

        try {
            $method->invoke(new BufferController(), 'SKU-SALAH', '261116F4QNC5FF');
            $this->fail('SKU seharusnya tidak valid.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Pesanan Shopee 261116F4QNC5FF: SKU SKU-SALAH tidak valid.',
                $e->errors()['produk_id'][0]
            );
        }
    }

    public function test_buffer_links_existing_project_in_another_cabang_instead_of_inserting_duplicate(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('marketplaces', function (Blueprint $table) {
            $table->id();
            $table->string('shop_id');
            $table->unsignedBigInteger('cabang_id');
        });
        Schema::create('project_mps', function (Blueprint $table) {
            $table->id();
            $table->string('nota')->unique();
            $table->unsignedBigInteger('marketplace_id');
            $table->unsignedBigInteger('cabang_id');
        });
        Schema::create('marketplace_buffers', function (Blueprint $table) {
            $table->id();
            $table->string('nota');
            $table->string('mp');
            $table->string('status');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('marketplace_id')->nullable();
            $table->timestamps();
        });

        DB::table('marketplaces')->insert(['id' => 10, 'shop_id' => '123', 'cabang_id' => 2]);
        DB::table('project_mps')->insert(['id' => 20, 'nota' => '261116F4QNC5FF', 'marketplace_id' => 10, 'cabang_id' => 1]);
        DB::table('marketplace_buffers')->insert(['id' => 30, 'nota' => '261116F4QNC5FF', 'mp' => 'shopee', 'status' => 'PROCESSED']);

        $controller = new class extends BufferController {
            protected function ambilApi($marketplace, $path, $param = [])
            {
                return ['response' => ['order_list' => [
                    ['order_sn' => '261116F4QNC5FF', 'message_to_seller' => ''],
                ]]];
            }
        };

        $method = new ReflectionMethod(BufferController::class, 'prosesBufferShop');
        with_cabang(2, function () use ($method, $controller) {
            $method->invoke($controller, Marketplace::withoutGlobalScope('cabang')->find(10),
                DB::table('marketplace_buffers')->get());
        });

        $this->assertSame(1, DB::table('project_mps')->count());
        $this->assertSame(20, DB::table('marketplace_buffers')->value('project_id'));
    }
}
