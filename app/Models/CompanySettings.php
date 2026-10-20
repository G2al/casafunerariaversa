<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySettings extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public static function current(): array
    {
        return array_replace([
            'viewing_venue' => 'Casa funeraria', 'viewing_address' => 'Via Savoia 31, Aversa',
            'closing_text' => 'Il presente vale anche per ringraziamento',
            'intro_male' => 'È venuto a mancare all’affetto dei suoi cari il Signor',
            'intro_female' => 'È venuta a mancare all’affetto dei suoi cari la Signora',
            'safe_left' => config('funeral.safe_area.left'), 'safe_top' => config('funeral.safe_area.top'),
            'safe_width' => config('funeral.safe_area.width'), 'safe_height' => config('funeral.safe_area.height'),
        ], static::find(1)?->data ?? []);
    }
}
