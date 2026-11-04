<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class SymbolFolder extends Model
{
    protected $guarded = ['id'];

    public function symbols(): HasMany
    {
        return $this->hasMany(Symbol::class);
    }

    public function isUsed(): bool
    {
        return $this->symbols()->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $folder) {
            if ($folder->isUsed()) {
                throw ValidationException::withMessages(['name' => 'La cartella contiene simboli: spostali o eliminali prima di eliminare la cartella.']);
            }
        });
    }
}
