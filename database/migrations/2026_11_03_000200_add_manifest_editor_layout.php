<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funeral_cases', fn (Blueprint $table) => $table->json('manifest_layout')->nullable());
    }

    public function down(): void
    {
        Schema::table('funeral_cases', fn (Blueprint $table) => $table->dropColumn('manifest_layout'));
    }
};
