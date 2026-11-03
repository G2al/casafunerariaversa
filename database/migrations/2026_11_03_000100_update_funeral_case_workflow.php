<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funeral_cases', function (Blueprint $table) {
            $table->boolean('transport_enabled')->default(true);
            $table->boolean('prior_transfer_enabled')->default(false);
            $table->string('death_certificate_path')->nullable();
            $table->string('istat_path')->nullable();
        });
        DB::table('funeral_cases')->where(function ($query) {
            foreach (['prior_transfer_destination', 'prior_transfer_date', 'certifying_doctor', 'prior_transfer_vehicle_id'] as $field) {
                $query->orWhere(function ($part) use ($field) {
                    $part->whereNotNull($field);
                    if (in_array($field, ['prior_transfer_destination', 'certifying_doctor'], true)) {
                        $part->where($field, '<>', '');
                    }
                });
            }
        })->update(['prior_transfer_enabled' => true]);
        DB::table('funeral_cases')->whereNull('transport_distance_km')->update(['transport_distance_km' => 100]);
    }

    public function down(): void
    {
        Schema::table('funeral_cases', fn (Blueprint $table) => $table->dropColumn(['transport_enabled', 'prior_transfer_enabled', 'death_certificate_path', 'istat_path']));
    }
};
