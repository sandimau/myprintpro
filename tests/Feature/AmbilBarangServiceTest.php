<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Controllers\Admin\AmbilBarangController;
use App\Services\AmbilBarangService;
use App\Services\ProduksiStatusService;
use App\Models\OrderDetail;
use App\Models\ProjectMpDetail;
use App\Models\Produksi;
use App\Services\ShopeeStockSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AmbilBarangServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        session(['cabang_id' => 1]);

        Schema::create('cabangs', function (Blueprint $t) { $t->id(); $t->string('kode'); $t->string('nama'); $t->boolean('status'); });
        Schema::create('produk_kategori_utamas', function (Blueprint $t) { $t->id(); $t->boolean('is_custom')->default(false); });
        Schema::create('produk_kategoris', function (Blueprint $t) { $t->id(); $t->string('nama'); $t->unsignedBigInteger('kategori_utama_id')->nullable(); $t->softDeletes(); });
        Schema::create('produk_models', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('kategori_id'); $t->string('nama'); $t->boolean('stok'); $t->timestamps(); $t->softDeletes(); });
        Schema::create('produks', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('produk_model_id'); $t->string('nama')->nullable(); $t->integer('hpp')->default(0); $t->boolean('status')->default(true); $t->timestamps(); });
        Schema::create('kontaks', function (Blueprint $t) { $t->id(); $t->string('nama'); $t->unsignedBigInteger('cabang_id')->nullable(); $t->boolean('marketplace')->nullable(); $t->timestamps(); $t->softDeletes(); });
        Schema::create('orders', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('kontak_id'); $t->unsignedBigInteger('cabang_id'); $t->string('nota'); $t->string('pengiriman')->nullable(); $t->boolean('marketplace')->nullable(); $t->timestamps(); $t->softDeletes(); });
        Schema::create('project_mps', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('order_id')->nullable(); $t->unsignedBigInteger('cabang_id'); $t->string('nota'); $t->string('konsumen')->nullable(); $t->string('shipping')->nullable(); $t->timestamps(); });
        Schema::create('marketplace_buffers', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('project_id'); $t->boolean('custom')->default(false); });
        Schema::create('produksis', function (Blueprint $t) { $t->id(); $t->string('nama'); $t->string('grup'); $t->timestamps(); $t->softDeletes(); });
        Schema::create('order_details', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('order_id'); $t->unsignedBigInteger('produk_id'); $t->integer('jumlah'); $t->integer('hpp')->nullable(); $t->string('material_ids')->nullable(); $t->timestamp('custom_resep_disalin_at')->nullable(); $t->unsignedBigInteger('produksi_id')->nullable(); $t->timestamps(); $t->softDeletes(); });
        Schema::create('project_mp_details', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('project_id'); $t->unsignedBigInteger('produk_id'); $t->integer('jumlah'); $t->integer('hpp')->nullable(); $t->string('material_ids')->nullable(); $t->timestamp('custom_resep_disalin_at')->nullable(); $t->unsignedBigInteger('produksi_id')->nullable(); $t->timestamps(); });
        Schema::create('produk_stoks', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('produk_id'); $t->unsignedBigInteger('cabang_id'); $t->integer('tambah'); $t->integer('kurang'); $t->integer('hpp')->nullable(); $t->string('kode')->nullable(); $t->string('keterangan')->nullable(); $t->unsignedBigInteger('detail_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->timestamps(); $t->softDeletes(); });
        Schema::create('produk_last_stoks', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('produk_id'); $t->unsignedBigInteger('cabang_id'); $t->integer('tahun'); $t->integer('saldo'); $t->timestamps(); });
        Schema::create('ambil_barangs', function (Blueprint $t) { $t->id(); $t->string('sumber'); $t->unsignedBigInteger('detail_id'); $t->unsignedBigInteger('produk_id'); $t->unsignedBigInteger('cabang_id'); $t->integer('jumlah'); $t->unsignedBigInteger('produk_stok_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->timestamp('created_at')->nullable(); $t->string('nota')->nullable(); $t->string('pembeli')->nullable(); $t->string('jenis')->nullable(); $t->string('shipping')->nullable(); $t->timestamp('tanggal_order')->nullable(); $t->unique(['sumber', 'detail_id']); });
        Schema::create('cek_stok_kosongs', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('produk_id'); $t->unsignedBigInteger('cabang_id'); $t->timestamp('created_at')->nullable(); });
        Schema::create('produk_custom_reseps', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('produk_id'); $t->string('jenis'); $t->unsignedBigInteger('komponen_model_id')->nullable(); $t->decimal('jumlah', 12, 2); $t->unsignedInteger('biaya')->nullable(); $t->boolean('per_order')->default(false); $t->timestamps(); });
        Schema::create('order_custom_bahans', function (Blueprint $t) { $t->id(); $t->string('sumber'); $t->unsignedBigInteger('detail_id'); $t->unsignedBigInteger('produk_id'); $t->integer('jumlah'); $t->integer('hpp'); $t->unsignedBigInteger('ambil_barang_id')->nullable(); $t->timestamps(); });

        DB::table('cabangs')->insert(['id' => 1, 'kode' => 'PUSAT', 'nama' => 'Pusat', 'status' => 1]);
        DB::table('produk_kategori_utamas')->insert(['id' => 1, 'is_custom' => 0]);
        DB::table('produk_kategoris')->insert(['id' => 1, 'nama' => 'Kategori', 'kategori_utama_id' => 1]);
        DB::table('produk_models')->insert([['id' => 1, 'kategori_id' => 1, 'nama' => 'Barang', 'stok' => 1], ['id' => 2, 'kategori_id' => 1, 'nama' => 'Custom', 'stok' => 0]]);
        DB::table('produks')->insert([['id' => 1, 'produk_model_id' => 1, 'nama' => 'A'], ['id' => 2, 'produk_model_id' => 2, 'nama' => 'B']]);
        DB::table('kontaks')->insert(['id' => 1, 'nama' => 'Pembeli', 'cabang_id' => 1]);
        DB::table('orders')->insert(['id' => 1, 'kontak_id' => 1, 'cabang_id' => 1, 'nota' => 'OFF1', 'pengiriman' => 'Kurir Lokal']);
        DB::table('project_mps')->insert(['id' => 1, 'cabang_id' => 1, 'nota' => 'MP1', 'konsumen' => 'Pembeli MP', 'shipping' => 'SPX']);
        DB::table('produksis')->insert(['id' => 1, 'nama' => 'Persiapan', 'grup' => 'awal']);
        DB::table('order_details')->insert([
            ['id' => 1, 'order_id' => 1, 'produk_id' => 1, 'jumlah' => 4, 'produksi_id' => 1],
            ['id' => 2, 'order_id' => 1, 'produk_id' => 2, 'jumlah' => 10, 'produksi_id' => 1],
        ]);
        DB::table('project_mp_details')->insert(['id' => 1, 'project_id' => 1, 'produk_id' => 1, 'jumlah' => 5, 'produksi_id' => 1]);
        DB::table('produk_stoks')->insert(['produk_id' => 1, 'cabang_id' => 1, 'tambah' => 6, 'kurang' => 0, 'created_at' => now()]);

        $user = new User();
        $user->id = 1;
        $user->setRelation('roles', collect([(object) ['name' => 'super']]));
        $this->be($user);
        $this->mock(ShopeeStockSyncService::class)->shouldReceive('markDirty')->zeroOrMoreTimes();
    }

    public function test_kebutuhan_digabung_dan_produk_tanpa_stok_dikecualikan(): void
    {
        $group = app(AmbilBarangService::class)->kebutuhan()->sole();
        $this->assertSame(9, $group['jumlah']);
        $this->assertSame(6, $group['stok']);
        $this->assertEqualsCanonicalizing(['order:1', 'project:1'], $group['items']->pluck('key')->all());
    }

    public function test_resep_custom_offline_dan_marketplace_muncul_di_halaman_sama_dan_dipotong_sekali(): void
    {
        DB::table('produk_kategori_utamas')->insert(['id' => 2, 'is_custom' => 1]);
        DB::table('produk_kategoris')->insert(['id' => 2, 'nama' => 'Produk Custom', 'kategori_utama_id' => 2]);
        DB::table('produk_models')->insert([
            ['id' => 3, 'kategori_id' => 1, 'nama' => 'Warna', 'stok' => 1],
            ['id' => 4, 'kategori_id' => 2, 'nama' => 'Custom', 'stok' => 0],
        ]);
        DB::table('produks')->insert([
            ['id' => 3, 'produk_model_id' => 3, 'nama' => 'Merah', 'hpp' => 25],
            ['id' => 4, 'produk_model_id' => 4, 'nama' => null, 'hpp' => 0],
            ['id' => 5, 'produk_model_id' => 3, 'nama' => 'Biru', 'hpp' => 30],
        ]);
        DB::table('produk_custom_reseps')->insert([
            ['produk_id' => 4, 'jenis' => 'bahan', 'komponen_model_id' => 3, 'jumlah' => 1.5, 'biaya' => null, 'per_order' => 0],
            ['produk_id' => 4, 'jenis' => 'internal', 'komponen_model_id' => null, 'jumlah' => 1, 'biaya' => 100, 'per_order' => 1],
        ]);
        DB::table('produk_stoks')->insert([
            ['produk_id' => 3, 'cabang_id' => 1, 'tambah' => 9, 'kurang' => 0, 'created_at' => now()],
            ['produk_id' => 5, 'cabang_id' => 1, 'tambah' => 9, 'kurang' => 0, 'created_at' => now()],
        ]);
        $custom = app(\App\Services\ProdukCustomService::class);
        $this->assertSame(['produk_id' => 4, 'material_ids' => [5], 'paket' => 2], $custom->parseSku('CUSTOM_4-5_2'));
        Gate::shouldReceive('allows')->once()->with('produk_access')->andReturn(true);
        $komponen = app(\App\Http\Controllers\Admin\ProdukCustomResepController::class)
            ->apiKomponen(Request::create('/admin/produk/4/resep/komponen', 'GET', ['q' => 'warna']), \App\Models\Produk::findOrFail(4));
        $this->assertSame([3], collect($komponen->getData(true))->pluck('id')->all());
        try {
            $custom->parseSku('CUSTOM_4-1');
            $this->fail('SKU dengan varian yang bukan komponen resep harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('produk_id', $e->errors());
        }
        $this->assertEqualsCanonicalizing(['CUSTOM_4-3', 'CUSTOM_4-5'], array_column($custom->skuOptions(4), 'sku'));
        DB::table('order_details')->insert(['id' => 3, 'order_id' => 1, 'produk_id' => 4, 'jumlah' => 2,
            'material_ids' => '[5]', 'produksi_id' => 1]);
        DB::table('project_mp_details')->insert(['id' => 2, 'project_id' => 1, 'produk_id' => 4, 'jumlah' => 3,
            'material_ids' => '[3]', 'produksi_id' => 1]);

        $service = app(AmbilBarangService::class);
        $items = $service->kebutuhan()->flatMap(fn ($g) => $g['items'])->pluck('key')->all();
        $this->assertContains('order:1', $items);
        $this->assertContains('order-bahan:1', $items);
        $this->assertContains('project-bahan:2', $items);
        $this->assertDatabaseHas('order_custom_bahans', ['sumber' => 'order', 'detail_id' => 3, 'produk_id' => 5, 'jumlah' => 3]);
        $this->assertDatabaseHas('order_custom_bahans', ['sumber' => 'project', 'detail_id' => 2, 'produk_id' => 3, 'jumlah' => 5]);
        $this->assertSame(95, (int) DB::table('order_details')->where('id', 3)->value('hpp'));

        $this->assertSame(2, $service->ambil(['order-bahan:1', 'project-bahan:2']));
        $this->assertSame(6, app(\App\Services\StokService::class)->saldoTersedia(5, 1));
        $this->assertSame(4, app(\App\Services\StokService::class)->saldoTersedia(3, 1));
        $this->assertDatabaseHas('ambil_barangs', ['sumber' => 'order-bahan', 'detail_id' => 1]);
        $this->assertDatabaseHas('ambil_barangs', ['sumber' => 'project-bahan', 'detail_id' => 2]);
        $this->assertCount(2, $service->arsip()->filter(fn ($item) => $item['jenis'] === 'Bahan custom'));
        $this->assertNotContains('order-bahan:1', $service->kebutuhan()->flatMap(fn ($g) => $g['items'])->pluck('key')->all());
        $this->expectException(ValidationException::class);
        $service->ambil(['order-bahan:1']);
    }

    public function test_order_marketplace_csv_tanpa_project_muncul_sebagai_bahan_custom(): void
    {
        DB::table('produk_kategori_utamas')->insert(['id' => 2, 'is_custom' => 1]);
        DB::table('produk_kategoris')->insert(['id' => 2, 'nama' => 'Custom', 'kategori_utama_id' => 2]);
        DB::table('produk_models')->insert(['id' => 3, 'kategori_id' => 2, 'nama' => 'Produk Custom', 'stok' => 0]);
        DB::table('produks')->insert(['id' => 3, 'produk_model_id' => 3, 'nama' => null]);
        DB::table('produk_custom_reseps')->insert(['produk_id' => 3, 'jenis' => 'bahan', 'komponen_model_id' => 1, 'jumlah' => 2]);
        DB::table('orders')->insert(['id' => 2, 'kontak_id' => 1, 'cabang_id' => 1, 'nota' => 'CSV1', 'marketplace' => 1]);
        DB::table('order_details')->insert(['id' => 3, 'order_id' => 2, 'produk_id' => 3, 'jumlah' => 2,
            'material_ids' => '[]', 'produksi_id' => 1]);

        $service = app(AmbilBarangService::class);
        $item = $service->kebutuhan()->flatMap(fn ($g) => $g['items'])
            ->firstWhere('key', 'order-bahan:1');
        $this->assertNotNull($item);
        $this->assertSame(4, $item['jumlah']);
        $this->assertSame(1, $service->ambil(['order-bahan:1']));
        $this->assertDatabaseHas('ambil_barangs', ['sumber' => 'order-bahan', 'jumlah' => 4]);
    }

    public function test_status_grup_produksi_menunggu_pengambilan_produk_stok_offline_dan_online(): void
    {
        DB::table('produksis')->insert(['id' => 2, 'nama' => 'PRINT_IDCARD', 'grup' => 'Produksi ID Card']);
        DB::table('produk_stoks')->insert(['produk_id' => 1, 'cabang_id' => 1, 'tambah' => 10, 'kurang' => 0, 'created_at' => now()]);
        $target = Produksi::findOrFail(2);
        $guard = app(ProduksiStatusService::class);

        foreach ([OrderDetail::findOrFail(1), ProjectMpDetail::findOrFail(1)] as $detail) {
            try {
                $guard->pastikanBahanDiambil($detail, $target);
                $this->fail('Produk stok tidak boleh masuk produksi sebelum diambil.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('produksi_id', $e->errors());
            }
        }
        app(AmbilBarangService::class)->ambil(['order:1', 'project:1']);
        $guard->pastikanBahanDiambil(OrderDetail::findOrFail(1), $target);
        $guard->apply(ProjectMpDetail::findOrFail(1), $target->id);
        $this->assertDatabaseHas('project_mp_details', ['id' => 1, 'produksi_id' => 2]);
    }

    public function test_status_grup_produksi_menunggu_seluruh_bahan_resep_custom(): void
    {
        DB::table('produksis')->insert(['id' => 2, 'nama' => 'PRINT_IDCARD', 'grup' => 'Produksi ID Card']);
        DB::table('produk_kategori_utamas')->insert(['id' => 2, 'is_custom' => 1]);
        DB::table('produk_kategoris')->insert(['id' => 2, 'nama' => 'Custom', 'kategori_utama_id' => 2]);
        DB::table('produk_models')->insert(['id' => 3, 'kategori_id' => 2, 'nama' => 'Produk Custom', 'stok' => 0]);
        DB::table('produk_models')->insert(['id' => 4, 'kategori_id' => 1, 'nama' => 'Bahan Kedua', 'stok' => 1]);
        DB::table('produks')->insert(['id' => 3, 'produk_model_id' => 3, 'nama' => null]);
        DB::table('produks')->insert(['id' => 4, 'produk_model_id' => 4, 'nama' => null]);
        DB::table('produk_custom_reseps')->insert(['produk_id' => 3, 'jenis' => 'bahan', 'komponen_model_id' => 1, 'jumlah' => 1]);
        DB::table('produk_custom_reseps')->insert(['produk_id' => 3, 'jenis' => 'bahan', 'komponen_model_id' => 4, 'jumlah' => 1]);
        DB::table('produk_stoks')->insert(['produk_id' => 4, 'cabang_id' => 1, 'tambah' => 9, 'kurang' => 0, 'created_at' => now()]);
        DB::table('order_details')->insert(['id' => 3, 'order_id' => 1, 'produk_id' => 3, 'jumlah' => 2,
            'material_ids' => '[]', 'produksi_id' => 1]);
        DB::table('project_mp_details')->insert(['id' => 2, 'project_id' => 1, 'produk_id' => 3, 'jumlah' => 2,
            'material_ids' => '[]', 'produksi_id' => 1]);
        app(\App\Services\ProdukCustomService::class)->snapshot('order', 3);
        app(\App\Services\ProdukCustomService::class)->snapshot('project', 2);
        $guard = app(ProduksiStatusService::class);
        $target = Produksi::findOrFail(2);

        foreach ([OrderDetail::findOrFail(3), ProjectMpDetail::findOrFail(2)] as $detail) {
            try {
                $guard->pastikanBahanDiambil($detail, $target);
                $this->fail('Bahan custom belum diambil.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('produksi_id', $e->errors());
            }
        }
        try {
            $guard->apply(ProjectMpDetail::findOrFail(2), $target->id);
            $this->fail('Online status must not advance before pickup.');
        } catch (ValidationException $e) {
            $this->assertDatabaseHas('project_mp_details', ['id' => 2, 'produksi_id' => 1]);
        }

        app(AmbilBarangService::class)->ambil(['order-bahan:1', 'project-bahan:3']);
        try {
            $guard->apply(ProjectMpDetail::findOrFail(2), $target->id);
            $this->fail('Tidak boleh masuk produksi jika baru sebagian bahan diambil.');
        } catch (ValidationException $e) {
            $this->assertDatabaseHas('project_mp_details', ['id' => 2, 'produksi_id' => 1]);
        }
        app(AmbilBarangService::class)->ambil(['order-bahan:2', 'project-bahan:4']);
        $guard->pastikanBahanDiambil(OrderDetail::findOrFail(3), $target);
        $guard->apply(ProjectMpDetail::findOrFail(2), $target->id);
        $this->assertDatabaseHas('project_mp_details', ['id' => 2, 'produksi_id' => 2]);
    }

    public function test_halaman_tidak_menimpa_variabel_cabangs_yang_dipakai_header(): void
    {
        Gate::shouldReceive('allows')->once()->with('produk_stok_access')->andReturn(true);
        $data = app(AmbilBarangController::class)->index(Request::create('/admin/ambil-barang'), app(AmbilBarangService::class))->getData();

        $this->assertArrayNotHasKey('cabangs', $data);
        $this->assertSame('Pusat', $data['namaCabangAmbilBarang'][1]);
        $this->assertSame(1, $data['cabangKelompok']->count());
    }

    public function test_tab_arsip_menampilkan_data_pengambilan_tanpa_filter_shipping(): void
    {
        Gate::shouldReceive('allows')->twice()->with('produk_stok_access')->andReturn(true);
        $controller = app(AmbilBarangController::class);
        $service = app(AmbilBarangService::class);
        $pending = $controller->index(Request::create('/admin/ambil-barang'), $service)->getData();
        $this->assertCount(2, $pending['cabangKelompok'][1]['Stok kurang']->first()['items']);

        $service->ambil(['order:1']);
        DB::table('orders')->where('id', 1)->update(['deleted_at' => now()]);
        DB::table('order_details')->where('id', 1)->update(['deleted_at' => now()]);
        $archive = $controller->index(Request::create('/admin/ambil-barang', 'GET', ['tab' => 'arsip']), $service)->getData();
        $item = $archive['arsipCabang'][1]->first()['items']->first();
        $this->assertSame('OFF1', $item['nota']);
        $this->assertSame('Pembeli', $item['pembeli']);
        $this->assertSame(4, $item['jumlah']);
    }

    public function test_tombol_opname_di_header_kelompok_memuat_beberapa_produk_di_modal(): void
    {
        $html = File::get(resource_path('views/admin/ambilBarang/index.blade.php'));

        $this->assertSame(1, substr_count($html, "route('ambilBarang.opname')"));
        $this->assertSame(1, substr_count($html, 'data-bs-target="#ambilBarangOpnameModal"'));
        $this->assertStringContainsString('data-bs-target="#ambilBarangOpnameModal"', $html);
        $this->assertStringContainsString('id="ambilBarangOpnameModal"', $html);
        $this->assertStringContainsString('name="items[{{ $indexOpname }}][stok_fisik]"', $html);
        $this->assertStringContainsString('name="items[{{ $indexOpname }}][pilih]"', $html);
        $this->assertStringNotContainsString('Shipping', $html);
        $this->assertStringNotContainsString('rowspan=', $html);
        $this->assertStringContainsString('Total produk', $html);
        $this->assertStringContainsString("@if (\$kategori !== 'Kosong di sistem')", $html);
    }

    public function test_produk_stok_kosong_masuk_kelompok_opname_tanpa_pengambilan(): void
    {
        DB::table('produk_stoks')->where('produk_id', 1)->update(['tambah' => 0]);
        Gate::shouldReceive('allows')->once()->with('produk_stok_access')->andReturn(true);

        $data = app(AmbilBarangController::class)->index(Request::create('/admin/ambil-barang'), app(AmbilBarangService::class))->getData();
        $this->assertTrue($data['cabangKelompok'][1]->has('Kosong di sistem'));
        $this->assertFalse($data['cabangKelompok'][1]->has('Stok kurang'));
    }

    public function test_boleh_ambil_sebagian_order_dan_tidak_boleh_dua_kali(): void
    {
        $service = app(AmbilBarangService::class);
        $this->assertSame(1, $service->ambil(['order:1']));
        $this->assertSame(2, DB::table('produk_stoks')->sum(DB::raw('tambah - kurang')));
        $this->assertSame('project:1', $service->kebutuhan()->sole()['items']->sole()['key']);

        $this->expectException(ValidationException::class);
        $service->ambil(['order:1']);
    }

    public function test_opname_beberapa_produk_sekaligus_dengan_satu_keterangan(): void
    {
        DB::table('produk_models')->insert(['id' => 3, 'kategori_id' => 1, 'nama' => 'Barang Kedua', 'stok' => 1]);
        DB::table('produks')->insert(['id' => 3, 'produk_model_id' => 3, 'nama' => 'C']);
        DB::table('produk_stoks')->insert(['produk_id' => 3, 'cabang_id' => 1, 'tambah' => 5, 'kurang' => 0, 'created_at' => now()]);

        Gate::shouldReceive('allows')->once()->with('produk_stok_access')->andReturn(true);
        Gate::shouldReceive('allows')->once()->with('opname_access')->andReturn(true);

        $request = Request::create('/admin/ambil-barang/opname', 'POST', [
            'items' => [
                ['produk_id' => 1, 'cabang_id' => 1, 'pilih' => 1, 'stok_fisik' => 9],
                ['produk_id' => 3, 'cabang_id' => 1, 'pilih' => 1, 'stok_fisik' => 2],
                ['produk_id' => 2, 'cabang_id' => 1],
            ],
            'keterangan' => 'Hasil hitung fisik',
        ]);
        app(AmbilBarangController::class)->opname($request, app(\App\Services\StokService::class));

        $this->assertDatabaseHas('produk_stoks', ['produk_id' => 1, 'kode' => 'opn', 'tambah' => 3]);
        $this->assertDatabaseHas('produk_stoks', ['produk_id' => 3, 'kode' => 'opn', 'kurang' => 3]);
        $this->assertDatabaseCount('produk_stoks', 4);
    }

    public function test_stok_tidak_cukup_membatalkan_seluruh_pengambilan(): void
    {
        try {
            app(AmbilBarangService::class)->ambil(['order:1', 'project:1']);
            $this->fail('Stok seharusnya tidak cukup.');
        } catch (ValidationException $exception) {
            $this->assertDatabaseCount('ambil_barangs', 0);
            $this->assertDatabaseCount('produk_stoks', 1);
        }
    }

    public function test_migrasi_menandai_mutasi_lama_tanpa_memotong_stok_ulang(): void
    {
        DB::table('produk_stoks')->insert([
            'produk_id' => 1, 'cabang_id' => 1, 'tambah' => 0, 'kurang' => 5,
            'kode' => 'shp', 'detail_id' => 1, 'created_at' => now(),
        ]);
        DB::table('orders')->insert(['id' => 2, 'kontak_id' => 1, 'cabang_id' => 1, 'nota' => 'OFF2']);
        DB::table('order_details')->insert(['id' => 3, 'order_id' => 2, 'produk_id' => 1, 'jumlah' => 3, 'produksi_id' => 1]);
        DB::table('produk_stoks')->insert([
            'produk_id' => 1, 'cabang_id' => 1, 'tambah' => 0, 'kurang' => 3,
            'kode' => 'jual', 'detail_id' => 2, 'created_at' => now(),
        ]);

        Schema::drop('ambil_barangs');
        Schema::drop('cek_stok_kosongs');
        $migration = require database_path('migrations/2026_11_16_000001_create_ambil_barangs_table.php');
        $migration->up();

        $this->assertDatabaseHas('ambil_barangs', ['sumber' => 'project', 'detail_id' => 1, 'jumlah' => 5]);
        $this->assertDatabaseHas('ambil_barangs', ['sumber' => 'order', 'detail_id' => 3, 'jumlah' => 3]);
        $this->assertDatabaseMissing('ambil_barangs', ['sumber' => 'order', 'detail_id' => 1]);
        $this->assertDatabaseCount('produk_stoks', 3);

        $archiveMigration = require database_path('migrations/2026_11_16_000002_add_archive_fields_to_ambil_barangs.php');
        $archiveMigration->up();
        $this->assertDatabaseHas('ambil_barangs', ['sumber' => 'order', 'detail_id' => 3, 'nota' => 'OFF2']);
        $this->assertDatabaseHas('ambil_barangs', ['sumber' => 'project', 'detail_id' => 1, 'nota' => 'MP1']);
    }

    public function test_mutasi_lama_yang_ambigu_ditolak_agar_tidak_terpotong_dua_kali(): void
    {
        DB::table('produk_stoks')->insert([
            'produk_id' => 1, 'cabang_id' => 1, 'tambah' => 0, 'kurang' => 4,
            'kode' => 'jual', 'detail_id' => 1,
            'keterangan' => 'barang dijual ke Pembeli', 'created_at' => now(),
        ]);

        try {
            app(AmbilBarangService::class)->ambil(['order:1']);
            $this->fail('Mutasi lama seharusnya perlu verifikasi.');
        } catch (ValidationException $exception) {
            $this->assertDatabaseCount('ambil_barangs', 0);
            $this->assertDatabaseCount('produk_stoks', 2);
        }
    }
}
