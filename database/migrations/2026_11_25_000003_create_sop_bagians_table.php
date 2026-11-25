<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sop_bagians', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });

        Schema::table('data_sops', function (Blueprint $table) {
            $table->foreignId('sop_bagian_id')->nullable()->after('bagian')->constrained('sop_bagians')->restrictOnDelete();
        });

        $namaBagian = [
            'depan' => 'Umum', 'marketing' => 'Marketing', 'setting' => 'Setting',
            'administrasi' => 'Administrasi', 'keuangan' => 'Keuangan', 'order' => 'Order',
            'desain' => 'Desain', 'peraturan' => 'Peraturan', 'pengiriman' => 'Pengiriman',
            'finishing' => 'Finishing', 'cetak' => 'Cetak', 'pricelist' => 'Daftar Harga',
        ];

        foreach ($namaBagian as $lama => $nama) {
            $id = DB::table('sop_bagians')->insertGetId([
                'nama' => $nama, 'slug' => Str::slug($nama),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('data_sops')->whereRaw('LOWER(TRIM(bagian)) = ?', [$lama])
                ->update(['sop_bagian_id' => $id]);
        }

        foreach (DB::table('data_sops')->whereNull('sop_bagian_id')->distinct()->pluck('bagian') as $asal) {
            $nama = trim((string) $asal);
            $nama = $nama === '' ? 'Umum' : $nama;
            $slug = Str::slug($nama);
            $id = DB::table('sop_bagians')->where('slug', $slug)->value('id');
            if (!$id) {
                $id = DB::table('sop_bagians')->insertGetId([
                    'nama' => $nama, 'slug' => $slug,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('data_sops')->whereNull('sop_bagian_id')
                ->where('bagian', $asal)
                ->update(['sop_bagian_id' => $id]);
        }

        Schema::table('data_sops', function (Blueprint $table) {
            $table->dropIndex(['bagian']);
            $table->dropColumn('bagian');
        });
    }

    public function down(): void
    {
        Schema::table('data_sops', function (Blueprint $table) {
            $table->string('bagian', 100)->nullable();
        });
        DB::table('data_sops')->join('sop_bagians', 'data_sops.sop_bagian_id', '=', 'sop_bagians.id')
            ->update(['data_sops.bagian' => DB::raw('sop_bagians.nama')]);
        Schema::table('data_sops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sop_bagian_id');
            $table->index('bagian');
        });
        Schema::dropIfExists('sop_bagians');
    }
};
