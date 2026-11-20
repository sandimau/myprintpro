<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jo_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produksi_id')->unique();
            $table->foreign('produksi_id')->references('id')->on('produksis')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jo_statuses');
    }
};
