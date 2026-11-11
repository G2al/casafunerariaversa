<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::table('funeral_cases', function (Blueprint $t) {
            $t->foreignId('room_id')->nullable()->constrained()->restrictOnDelete();
            $t->dateTime('transport_ends_at')->nullable();
            $t->index(['room_id', 'viewing_starts_at', 'viewing_ends_at'], 'case_room_interval');
        });
        Schema::table('vehicle_commitments', function (Blueprint $t) {
            $t->foreignId('vehicle_id')->nullable()->constrained('funeral_vehicles')->restrictOnDelete();
            $t->foreignId('technical_director_id')->nullable()->constrained('funeral_staff_members')->restrictOnDelete();
            $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable();
            $t->boolean('overlap_forced')->default(false);
            $t->index(['vehicle_id', 'starts_at', 'ends_at'], 'vehicle_booking_interval');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_commitments', function (Blueprint $t) {
            $t->dropIndex('vehicle_booking_interval');
            $t->dropConstrainedForeignId('vehicle_id');
            $t->dropConstrainedForeignId('technical_director_id');
            $t->dropColumn(['starts_at', 'ends_at', 'overlap_forced']);
        });
        Schema::table('funeral_cases', function (Blueprint $t) {
            $t->dropIndex('case_room_interval');
            $t->dropConstrainedForeignId('room_id');
            $t->dropColumn('transport_ends_at');
        });
        Schema::dropIfExists('rooms');
    }
};
