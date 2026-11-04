<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funeral_cases', fn (Blueprint $t) => $t->json('prelapide_layout')->nullable());
        Schema::create('symbols', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('image_path');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('editor_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('funeral_case_id')->constrained()->cascadeOnDelete();
            $t->string('image_path');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editor_attachments');
        Schema::dropIfExists('symbols');
        Schema::table('funeral_cases', fn (Blueprint $t) => $t->dropColumn('prelapide_layout'));
    }
};
