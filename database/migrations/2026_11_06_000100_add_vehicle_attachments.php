<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funeral_vehicles', function (Blueprint $table) {
            $table->json('attachments')->nullable();
            $table->json('attachment_names')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('funeral_vehicles', fn (Blueprint $table) => $table->dropColumn(['attachments', 'attachment_names']));
    }
};
