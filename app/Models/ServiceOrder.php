<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['items' => 'array', 'revision' => 'integer', 'submitted_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public static function coffinTypes(): array
    {
        return ['wood' => 'Sola cassa di legno foderata internamente', 'biodegradable' => 'Cassa di legno con contenitore biodegradabile', 'double' => 'Duplice cassa di legno e zinco'];
    }

    public static function defaults(): array
    {
        return array_map(fn ($name) => ['name' => $name, 'selected' => false, 'quantity' => null], ['Vite a corvi', 'Croce normale', 'Tappi', 'Valvola', 'Zinco', 'Sacco zincato', 'Tappezzeria', 'Targhetta', 'Materassino', 'Polvere assorbente', 'Barriera']);
    }
}
