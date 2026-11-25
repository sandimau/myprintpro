<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SopBagian extends Model
{
    protected $table = 'sop_bagians';

    protected $fillable = ['nama', 'slug'];

    public function sops(): HasMany
    {
        return $this->hasMany(DataSop::class, 'sop_bagian_id');
    }
}
