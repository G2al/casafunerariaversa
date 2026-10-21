<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funeral_cases', function (Blueprint $table) {
            $table->string('identity_document_type')->nullable();
            $table->string('identity_front_path')->nullable();
            $table->string('identity_back_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('funeral_cases', fn (Blueprint $table) => $table->dropColumn(['identity_document_type', 'identity_front_path', 'identity_back_path']));
    }
};
