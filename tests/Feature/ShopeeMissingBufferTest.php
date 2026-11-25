<?php

namespace Tests\Feature;

use App\Http\Controllers\Webhook\ShopeeMissingBufferController;
use App\Http\Middleware\LoadSistemConfig;
use App\Http\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopeeMissingBufferTest extends TestCase
{
    public function test_route_requires_login(): void
    {
        $this->withoutMiddleware(LoadSistemConfig::class);
        $this->get('/shopee/cek-nota-terlewat')->assertRedirect('/login');
    }

    public function test_reports_only_active_orders_not_in_buffer_or_project_across_pages(): void
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
        });
        Schema::create('project_mps', function (Blueprint $table) {
            $table->id();
            $table->string('nota');
        });

        DB::table('marketplaces')->insert(['id' => 1, 'marketplace' => 'shopee', 'shop_id' => '123', 'nama' => 'Toko A']);
        DB::table('marketplace_buffers')->insert(['nota' => 'ADA']);
        DB::table('project_mps')->insert(['nota' => 'SUDAH_DIPROSES']);

        $stub = new class extends ShopeeMissingBufferController {
            public array $calls = [];

            protected function ambilApi($marketplace, $path, $param = [])
            {
                $this->calls[] = [$path, $param];

                return ['response' => empty($param['cursor']) ? [
                    'order_list' => [
                        ['order_sn' => 'ADA', 'order_status' => 'PROCESSED'],
                        ['order_sn' => 'SUDAH_DIPROSES', 'order_status' => 'SHIPPED'],
                        ['order_sn' => 'HILANG', 'order_status' => 'READY_TO_SHIP'],
                    ],
                    'more' => true, 'next_cursor' => 'next',
                ] : [
                    'order_list' => [
                        ['order_sn' => 'SELESAI', 'order_status' => 'COMPLETED'],
                        ['order_sn' => 'BATAL', 'order_status' => 'CANCELLED'],
                        ['order_sn' => 'HILANG_2', 'order_status' => 'UNPAID'],
                    ],
                    'more' => false,
                ]];
            }
        };
        $this->app->instance(ShopeeMissingBufferController::class, $stub);
        $this->withoutMiddleware([Authenticate::class, LoadSistemConfig::class]);

        $this->getJson('/shopee/cek-nota-terlewat?marketplace_id=1')
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('total_missing', 2)
            ->assertJsonPath('missing.0.nota', 'HILANG')
            ->assertJsonPath('missing.1.nota', 'HILANG_2');

        $this->assertCount(2, $stub->calls);
        $this->assertSame('order/get_order_list', $stub->calls[0][0]);
        $this->assertSame('next', $stub->calls[1][1]['cursor']);
        $this->assertSame(1, DB::table('marketplace_buffers')->count());
    }
}
