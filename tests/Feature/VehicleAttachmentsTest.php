<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FuneralVehicleResource\Pages\ManageVehicles;
use App\Models\FuneralCase;
use App\Models\FuneralVehicle;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleAttachmentsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    public function test_multiple_uploads_are_optimized_private_and_editable(): void
    {
        Livewire::test(ManageVehicles::class)->callAction(CreateAction::class, data: [
            'name' => 'Carro funebre', 'plate' => 'AB123CD', 'is_active' => true,
            'attachments' => [UploadedFile::fake()->image('esterno.jpg', 200, 100), UploadedFile::fake()->image('interno.png', 100, 100), UploadedFile::fake()->createWithContent('libretto.pdf', "%PDF-1.4\nAllegato di prova")],
        ])->assertHasNoActionErrors();
        $vehicle = FuneralVehicle::firstOrFail();
        $paths = $vehicle->attachments;
        $this->assertCount(3, $paths);
        $this->assertStringEndsWith('.webp', $paths[0]);
        $this->assertStringEndsWith('.webp', $paths[1]);
        $this->assertStringEndsWith('.pdf', $paths[2]);
        $this->assertSame('esterno.jpg', $vehicle->attachment_names[$paths[0]]);
        Storage::disk('local')->assertExists($paths);
        foreach (array_keys($paths) as $index) {
            $this->get(route('private.vehicle-attachment', [$vehicle, $index]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        Livewire::test(ManageVehicles::class)->callAction(TestAction::make('edit')->table($vehicle), data: [
            'attachments' => [$paths[2], $paths[1]],
        ])->assertHasNoActionErrors();
        $this->assertSame([$paths[2], $paths[1]], $vehicle->fresh()->attachments);
        Storage::disk('local')->assertMissing($paths[0]);
        Storage::disk('local')->assertExists([$paths[1], $paths[2]]);
        $vehicle->fresh()->delete();
        Storage::disk('local')->assertMissing($paths);
    }

    public function test_files_require_admin_access_and_a_valid_vehicle_index(): void
    {
        Storage::disk('local')->put('vehicle-attachments/file.pdf', "%PDF-1.4\nProva");
        $vehicle = FuneralVehicle::create(['name' => 'Mezzo', 'attachments' => ['vehicle-attachments/file.pdf']]);
        $this->get(route('private.vehicle-attachment', [$vehicle, 5]))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => UserRole::Operator]));
        $this->get(route('private.vehicle-attachment', [$vehicle, 0]))->assertForbidden();
        auth()->logout();
        $this->get(route('private.vehicle-attachment', [$vehicle, 0]))->assertRedirect();
    }

    public function test_transaction_rollback_and_used_vehicle_keep_their_files(): void
    {
        $path = 'vehicle-attachments/file.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\nProva");
        $vehicle = FuneralVehicle::create(['name' => 'Mezzo', 'attachments' => [$path]]);
        DB::beginTransaction();
        $vehicle->update(['attachments' => []]);
        Storage::disk('local')->assertExists($path);
        DB::rollBack();
        $vehicle->refresh();
        $this->assertSame([$path], $vehicle->attachments);
        FuneralCase::create(['type' => 'standard_transport', 'vehicle_id' => $vehicle->id]);
        try {
            $vehicle->delete();
            $this->fail('Un mezzo utilizzato non deve essere eliminato.');
        } catch (ValidationException) {
            Storage::disk('local')->assertExists($path);
            $this->assertNotNull($vehicle->fresh());
        }
    }

    public function test_paths_outside_vehicle_storage_are_rejected(): void
    {
        Storage::disk('local')->put('identity-documents/other.pdf', "%PDF-1.4\nProva");
        $this->expectException(ValidationException::class);
        FuneralVehicle::create(['name' => 'Mezzo', 'attachments' => ['identity-documents/other.pdf']]);
    }
}
