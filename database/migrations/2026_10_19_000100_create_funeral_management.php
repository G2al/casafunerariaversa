<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
        });
        // Audit iniziale: l'unico account preesistente è il primo amministratore.
        if (DB::table('users')->count() === 1) {
            DB::table('users')->update(['role' => 'admin']);
        }
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->timestamps();
        });
        foreach (['funeral_staff_members', 'funeral_vehicles'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('name');
                $table->string($name === 'funeral_vehicles' ? 'plate' : 'qualification')->nullable();
                $table->string('credentials')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        Schema::create('case_sequences', function (Blueprint $table) {
            $table->unsignedInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
        Schema::create('manifest_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('image_path');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('funeral_cases', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('draft')->index();
            foreach (config('funeral.sections') as $fields) {
                foreach ($fields as $field => $label) {
                    if (str_ends_with($field, '_at')) {
                        $table->dateTime($field)->nullable();
                    } elseif (str_ends_with($field, '_date')) {
                        $table->date($field)->nullable();
                    } elseif ($field === 'death_cause') {
                        $table->text($field)->nullable();
                    } else {
                        $table->string($field)->nullable();
                    }
                }
            }
            $table->string('photo_path')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('family_members')->nullable();
            $table->json('manifest_options')->nullable();
            $table->foreignId('manifest_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained('funeral_staff_members')->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('funeral_vehicles')->restrictOnDelete();
            $table->dateTime('registered_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['operator_id', 'created_at']);
            $table->index('death_date');
            $table->index(['last_name', 'first_name']);
        });
        Schema::create('funeral_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funeral_case_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('kind');
            $table->string('case_type');
            $table->unsignedInteger('version');
            $table->foreignId('manifest_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('file_path')->unique();
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['funeral_case_id', 'kind', 'version']);
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('funeral_case_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event');
            $table->json('changed_fields')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'funeral_documents', 'funeral_cases', 'manifest_templates', 'case_sequences', 'funeral_vehicles', 'funeral_staff_members', 'company_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
