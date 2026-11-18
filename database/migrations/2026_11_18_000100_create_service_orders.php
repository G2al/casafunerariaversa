<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('funeral_case_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('case_code');
            $t->string('deceased_name');
            $t->string('coffin_name');
            $t->string('coffin_type');
            $t->json('items');
            $t->string('status')->default('draft')->index();
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('confirmed_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('service_order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->json('snapshot');
            $t->timestamp('created_at');
        });
        DB::table('funeral_cases')->whereNull('transport_at')->whereNotNull('transfer_at')->update(['transport_at' => DB::raw('transfer_at')]);
        DB::table('funeral_cases')->whereNotNull('transport_at')->update(['transfer_at' => DB::raw('transport_at')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_events');
        Schema::dropIfExists('service_orders');
    }
};
