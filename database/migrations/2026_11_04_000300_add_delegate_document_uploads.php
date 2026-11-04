<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funeral_cases', function (Blueprint $t) {
            $t->string('delegate_identity_front_path')->nullable();
            $t->string('delegate_identity_back_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('funeral_cases', fn (Blueprint $t) => $t->dropColumn(['delegate_identity_front_path', 'delegate_identity_back_path']));
    }
};
