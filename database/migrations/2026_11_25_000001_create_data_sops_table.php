<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sops', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->longText('isi');
            $table->string('bagian', 100)->nullable();
            $table->boolean('global')->default(false);
            $table->timestamps();
            $table->index('bagian');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sops');
    }
};
