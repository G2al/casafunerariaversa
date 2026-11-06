<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funeral_case_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('snapshot');
            $table->json('confirmed_snapshot')->nullable();
            $table->string('fingerprint', 64);
            $table->uuid('attempt_id');
            $table->string('status', 20);
            $table->string('message_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_commitments');
    }
};
