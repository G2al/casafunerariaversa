<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Room extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleting(function (self $room) {
            if (FuneralCase::withTrashed()->where('room_id', $room->id)->exists()) {
                throw ValidationException::withMessages(['name' => 'La stanza è associata a una pratica e non può essere eliminata.']);
            }
        });
    }
}
