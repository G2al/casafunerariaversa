<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleCommitment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'confirmed_snapshot' => 'array', 'attempted_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
