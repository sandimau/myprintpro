<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DataSopController;
use App\Models\DataSop;
use App\Models\SopBagian;
use App\Services\DataSopOrganizer;
use App\Support\SopHtml;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataSopTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/2026_11_25_000001_create_data_sops_table.php'))->up();
        (require database_path('migrations/2026_11_25_000003_create_sop_bagians_table.php'))->up();
    }

    public function test_daftar_bisa_dicari_dan_difilter_per_bagian(): void
    {
        $setting = SopBagian::where('slug', 'setting')->firstOrFail();
        $gudang = SopBagian::create(['nama' => 'Gudang', 'slug' => 'gudang']);
        DataSop::create(['judul' => 'Cek desain', 'isi' => '<p>Periksa cetakan</p>', 'sop_bagian_id' => $setting->id]);
        DataSop::create(['judul' => 'Cek stok', 'isi' => 'Periksa bahan', 'sop_bagian_id' => $gudang->id]);

        $controller = new DataSopController();
        $bagians = $controller->index(Request::create('/admin/data-sops'))->getData()['bagians'];
        $this->assertCount(13, $bagians);
        $response = $controller->bagian(Request::create('/admin/data-sops/bagian/setting?cari=cetakan'), $setting);
        $this->assertSame(1, $response->getData()['sops']->total());
        $this->assertSame('Cek desain', $response->getData()['sops']->first()->judul);
    }

    public function test_duplikat_per_bagian_dihapus_dan_konten_terlengkap_disimpan(): void
    {
        Storage::fake('local');
        $peraturan = SopBagian::where('slug', 'peraturan')->firstOrFail();
        $administrasi = SopBagian::where('slug', 'administrasi')->firstOrFail();
        DataSop::create(['judul' => 'Cuti', 'isi' => 'Versi singkat', 'sop_bagian_id' => $peraturan->id]);
        DataSop::create(['judul' => 'CUTI', 'isi' => 'Versi paling lengkap dan rinci', 'sop_bagian_id' => $peraturan->id]);
        DataSop::create(['judul' => 'Cuti', 'isi' => 'Aturan divisi lain', 'sop_bagian_id' => $administrasi->id]);
        DataSop::create(['judul' => 'Hari &amp;Jam Kerja', 'isi' => 'Lama', 'sop_bagian_id' => $peraturan->id]);
        DataSop::create(['judul' => 'HARI & JAM KERJA', 'isi' => 'Lebih lengkap', 'sop_bagian_id' => $peraturan->id]);

        $hasil = (new DataSopOrganizer())->run();

        $this->assertSame(2, $hasil['hapus']);
        $this->assertSame(3, DataSop::count());
        $this->assertSame('Versi paling lengkap dan rinci', DataSop::where('sop_bagian_id', $peraturan->id)->where('judul', 'CUTI')->value('isi'));
        $this->assertSame(1, DataSop::where('sop_bagian_id', $administrasi->id)->count());
        Storage::disk('local')->assertExists(str_replace(storage_path('app/'), '', $hasil['backup']));
    }

    public function test_bisa_menambah_dan_memperbarui_sop(): void
    {
        Gate::shouldReceive('authorize')->twice()->with('sop_manage');
        $controller = new DataSopController();
        $bagian = SopBagian::where('slug', 'cetak')->firstOrFail();
        $controller->store(Request::create('/admin/data-sops', 'POST', [
            'judul' => 'Prosedur Baru', 'isi' => '<p>Langkah pertama</p>',
            'sop_bagian_id' => $bagian->id, 'global' => '1', 'company_id' => 10,
        ]));

        $sop = DataSop::firstOrFail();
        $this->assertTrue($sop->global);
        $this->assertSame('Cetak', $sop->bagian->nama);
        $this->assertFalse(array_key_exists('company_id', $sop->getAttributes()));

        $controller->update(Request::create('/admin/data-sops/'.$sop->id, 'PUT', [
            'judul' => 'Prosedur Revisi', 'isi' => 'Langkah kedua',
            'sop_bagian_id' => $bagian->id,
        ]), $sop);
        $this->assertSame('Prosedur Revisi', $sop->fresh()->judul);
        $this->assertFalse($sop->fresh()->global);
    }

    public function test_bagian_tidak_dihapus_tanpa_konfirmasi_jika_masih_ada_aturan(): void
    {
        Gate::shouldReceive('authorize')->times(3)->with('sop_manage');
        $controller = new DataSopController();
        $controller->storeBagian(Request::create('/admin/data-sops/bagians', 'POST', ['nama' => 'Quality Control']));
        $bagian = SopBagian::where('slug', 'quality-control')->firstOrFail();
        $controller->updateBagian(Request::create('/admin/data-sops/bagians/'.$bagian->id, 'PUT', ['nama' => 'Kontrol Kualitas']), $bagian);
        $this->assertSame('kontrol-kualitas', $bagian->fresh()->slug);

        DataSop::create(['judul' => 'Cek hasil', 'isi' => 'Periksa hasil', 'sop_bagian_id' => $bagian->id]);
        $controller->destroyBagian(Request::create('/admin/data-sops/bagians/'.$bagian->id, 'DELETE'), $bagian);
        $this->assertNotNull($bagian->fresh());
        $this->assertSame(1, $bagian->sops()->count());
    }

    public function test_edit_bagian_melalui_popup_kembali_ke_daftar_bagian(): void
    {
        Gate::shouldReceive('authorize')->once()->with('sop_manage');
        $bagian = SopBagian::where('slug', 'setting')->firstOrFail();

        $response = (new DataSopController())->updateBagian(Request::create('/admin/data-sops/bagians/'.$bagian->id, 'PUT', [
            'nama' => 'Pra Cetak', '_sop_action' => 'edit',
        ]), $bagian);

        $this->assertSame('Pra Cetak', $bagian->fresh()->nama);
        $this->assertSame(route('data-sops.index'), $response->getTargetUrl());
    }

    public function test_hapus_bagian_menghapus_semua_aturan_dengan_cadangan_dan_tidak_mengganggu_bagian_lain(): void
    {
        Storage::fake('local');
        Gate::shouldReceive('authorize')->once()->with('sop_manage');
        $bagian = SopBagian::where('slug', 'peraturan')->firstOrFail();
        $lain = SopBagian::where('slug', 'cetak')->firstOrFail();
        $satu = DataSop::create(['judul' => 'Aturan satu', 'isi' => '<p>Isi satu</p>', 'sop_bagian_id' => $bagian->id]);
        DataSop::create(['judul' => 'Aturan dua', 'isi' => 'Isi dua', 'sop_bagian_id' => $bagian->id]);
        DataSop::create(['judul' => 'Aturan lain', 'isi' => 'Tetap ada', 'sop_bagian_id' => $lain->id]);

        (new DataSopController())->destroyBagian(Request::create('/admin/data-sops/bagians/'.$bagian->id, 'DELETE', [
            'hapus_aturan' => '1',
        ]), $bagian);

        $this->assertNull($bagian->fresh());
        $this->assertNull($satu->fresh());
        $this->assertSame(1, DataSop::count());
        $backups = Storage::disk('local')->files('sop-backups');
        $this->assertCount(1, $backups);
        $this->assertStringContainsString('Aturan satu', Storage::disk('local')->get($backups[0]));
    }

    public function test_hapus_aturan_satu_satu_tidak_menghapus_bagiannya(): void
    {
        Gate::shouldReceive('authorize')->once()->with('sop_manage');
        $bagian = SopBagian::where('slug', 'setting')->firstOrFail();
        $sop = DataSop::create(['judul' => 'Aturan satu', 'isi' => 'Isi', 'sop_bagian_id' => $bagian->id]);

        $response = (new DataSopController())->destroy($sop);

        $this->assertNull($sop->fresh());
        $this->assertNotNull($bagian->fresh());
        $this->assertSame(route('data-sops.bagian', $bagian->slug), $response->getTargetUrl());
    }

    public function test_editor_menampilkan_format_lama_tanpa_menjalankan_html_berbahaya(): void
    {
        $html = '<p onclick="alert(1)">Aturan <strong>penting</strong></p>'
            . '<table><tr><td>Isi</td><td>Nilai</td></tr></table>'
            . '<a href="javascript:alert(1)">tautan</a><script>alert(2)</script>';
        $clean = SopHtml::clean($html);

        $this->assertStringContainsString('<strong>penting</strong>', $clean);
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringContainsString('tautan', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<script', $clean);
    }

    public function test_nomor_manual_di_dalam_daftar_bernomor_tidak_ditampilkan_dua_kali(): void
    {
        $html = '<ol style="list-style:none"><li>' . "\n  " . '<span>1.&nbsp; Menghitung pajak</span></li>'
            . '<li><span>2. Menerbitkan faktur</span></li></ol>';

        $clean = SopHtml::clean($html);

        $this->assertStringContainsString('<ol>', $clean);
        $this->assertStringContainsString('Menghitung pajak', $clean);
        $this->assertStringNotContainsString('1.', $clean);
        $this->assertStringNotContainsString('2.', $clean);
    }

    public function test_tabel_layout_satu_sel_dilepas(): void
    {
        $html = '<table><tbody><tr><td><ol><li>1. Menghitung pajak</li></ol></td></tr></tbody></table>';
        $clean = SopHtml::clean($html);
        $this->assertStringNotContainsString('<table', $clean);
        $this->assertStringContainsString('<ol><li>Menghitung pajak</li></ol>', $clean);
    }
}
