<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symbol_folders', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::table('symbols', function (Blueprint $t) {
            $t->foreignId('symbol_folder_id')->nullable()->constrained()->restrictOnDelete();
        });
        if (DB::table('symbols')->exists()) {
            $id = DB::table('symbol_folders')->insertGetId(['name' => 'Generali', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('symbols')->update(['symbol_folder_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('symbols', fn (Blueprint $t) => $t->dropConstrainedForeignId('symbol_folder_id'));
        Schema::dropIfExists('symbol_folders');
    }
};
