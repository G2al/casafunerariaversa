<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManifestExport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function manifestRecord()
    {
        return $this->belongsTo(ManifestRecord::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Le versioni PDF sono immutabili.'));
        static::deleting(fn () => throw new \LogicException('Le versioni PDF devono essere conservate.'));
    }
}
