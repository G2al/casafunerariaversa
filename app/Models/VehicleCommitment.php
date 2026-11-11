<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleCommitment extends Model
{
    protected $guarded = ['id'];

    public function funeralCase()
    {
        return $this->belongsTo(FuneralCase::class);
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'confirmed_snapshot' => 'array', 'attempted_at' => 'datetime', 'sent_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'overlap_forced' => 'boolean'];
    }
}
