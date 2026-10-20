<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuneralDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function funeralCase()
    {
        return $this->belongsTo(FuneralCase::class)->withTrashed();
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Le versioni PDF sono immutabili.'));
        static::deleting(fn () => throw new \LogicException('Le versioni PDF devono essere conservate.'));
    }
}
