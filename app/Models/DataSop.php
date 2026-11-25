<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSop extends Model
{
    protected $table = 'data_sops';

    protected $fillable = ['judul', 'isi', 'sop_bagian_id', 'global'];

    protected $casts = ['global' => 'boolean'];

    public function bagian(): BelongsTo
    {
        return $this->belongsTo(SopBagian::class, 'sop_bagian_id');
    }
}
