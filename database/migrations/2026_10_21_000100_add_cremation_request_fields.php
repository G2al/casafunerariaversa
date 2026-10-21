<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funeral_staff_members', function (Blueprint $table) {
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('residence_city')->nullable();
            $table->string('residence_address')->nullable();
            $table->boolean('is_technical_director')->default(false);
        });
        Schema::table('funeral_cases', function (Blueprint $table) {
            $table->foreignId('technical_director_id')->nullable()->constrained('funeral_staff_members')->restrictOnDelete();
            $table->string('transport_scope')->nullable();
            $table->string('transport_destination_city')->nullable();
            $table->decimal('transport_distance_km', 8, 2)->nullable();
            $table->string('prior_transfer_destination')->nullable();
            $table->date('prior_transfer_date')->nullable();
            $table->string('certifying_doctor')->nullable();
            $table->foreignId('prior_transfer_vehicle_id')->nullable()->constrained('funeral_vehicles')->restrictOnDelete();
            $table->string('transport_request_note')->nullable();
            $table->string('transport_attachment_extra_1')->nullable();
            $table->string('transport_attachment_extra_2')->nullable();
        });
        Schema::create('funeral_case_staff', function (Blueprint $table) {
            $table->foreignId('funeral_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funeral_staff_member_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['funeral_case_id', 'funeral_staff_member_id'], 'case_staff_primary');
        });
        DB::table('funeral_cases')->whereNotNull('staff_member_id')->select(['id', 'staff_member_id'])->orderBy('id')->chunkById(100, function ($cases) {
            DB::table('funeral_case_staff')->insert($cases->map(fn ($case) => [
                'funeral_case_id' => $case->id, 'funeral_staff_member_id' => $case->staff_member_id,
                'created_at' => now(), 'updated_at' => now(),
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funeral_case_staff');
        Schema::table('funeral_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technical_director_id');
            $table->dropConstrainedForeignId('prior_transfer_vehicle_id');
            $table->dropColumn(['transport_scope', 'transport_destination_city', 'transport_distance_km', 'prior_transfer_destination', 'prior_transfer_date', 'certifying_doctor', 'transport_request_note', 'transport_attachment_extra_1', 'transport_attachment_extra_2']);
        });
        Schema::table('funeral_staff_members', fn (Blueprint $table) => $table->dropColumn(['birth_date', 'birth_place', 'residence_city', 'residence_address', 'is_technical_director']));
    }
};
