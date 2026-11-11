<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manifest_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('source_case_id')->nullable()->constrained('funeral_cases')->nullOnDelete();
            $t->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('data');
            $t->json('manifest_layout')->nullable();
            $t->timestamps();
        });
        Schema::table('funeral_cases', fn (Blueprint $t) => $t->foreignId('manifest_record_id')->nullable()->constrained()->nullOnDelete());
        Schema::table('editor_attachments', function (Blueprint $t) {
            $t->unsignedBigInteger('funeral_case_id')->nullable()->change();
            $t->foreignId('manifest_record_id')->nullable()->constrained()->cascadeOnDelete();
        });
        Schema::create('manifest_exports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('manifest_record_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('version');
            $t->string('file_path');
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['manifest_record_id', 'version']);
        });
        $fields = ['type', 'first_name', 'last_name', 'sex', 'birth_date', 'death_date', 'spouse_name', 'marital_status', 'photo_path', 'manifest_template_id', 'manifest_options', 'family_members', 'viewing_venue', 'viewing_address', 'viewing_starts_at', 'viewing_ends_at', 'church', 'ceremony_city', 'ceremony_at', 'transfer_place', 'transfer_at'];
        DB::table('funeral_cases')->orderBy('id')->chunkById(100, function ($cases) use ($fields) {
            foreach ($cases as $case) {
                $data = array_intersect_key((array) $case, array_flip($fields));
                foreach (['manifest_options', 'family_members'] as $f) {
                    $data[$f] = is_string($data[$f] ?? null) ? json_decode($data[$f], true) : ($data[$f] ?? null);
                }
                $id = DB::table('manifest_records')->insertGetId(['source_case_id' => $case->id, 'operator_id' => $case->operator_id, 'created_by' => $case->created_by,
                    'data' => json_encode($data, JSON_THROW_ON_ERROR), 'manifest_layout' => $case->manifest_layout, 'created_at' => $case->created_at, 'updated_at' => $case->updated_at]);
                DB::table('funeral_cases')->where('id', $case->id)->update(['manifest_record_id' => $id]);
                // I riferimenti agli upload vengono copiati, senza spostare o modificare gli originali.
                $mapping = [];
                foreach (DB::table('editor_attachments')->where('funeral_case_id', $case->id)->get() as $a) {
                    $newId = DB::table('editor_attachments')->insertGetId(['manifest_record_id' => $id, 'funeral_case_id' => null, 'image_path' => $a->image_path, 'created_at' => $a->created_at, 'updated_at' => $a->updated_at]);
                    $mapping['upload_'.$a->id] = 'upload_'.$newId;
                }
                $layout = $case->manifest_layout ? json_decode($case->manifest_layout, true) : null;
                if ($layout) {
                    foreach ($layout['scene']['elements'] as &$element) {
                        if (isset($mapping[$element['asset'] ?? ''])) {
                            $element['asset'] = $mapping[$element['asset']];
                        }
                    }
                    unset($element);
                    foreach ($mapping as $old => $new) {
                        if (isset($layout['assets'][$old])) {
                            $layout['assets'][$new] = $layout['assets'][$old];
                            unset($layout['assets'][$old]);
                        }
                    }
                    DB::table('manifest_records')->where('id', $id)->update(['manifest_layout' => json_encode($layout, JSON_THROW_ON_ERROR)]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifest_exports');
        DB::table('editor_attachments')->whereNull('funeral_case_id')->delete();
        Schema::table('editor_attachments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('manifest_record_id');
            $t->unsignedBigInteger('funeral_case_id')->nullable(false)->change();
        });
        Schema::table('funeral_cases', fn (Blueprint $t) => $t->dropConstrainedForeignId('manifest_record_id'));
        Schema::dropIfExists('manifest_records');
    }
};
