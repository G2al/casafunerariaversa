<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FuneralCaseResource\Pages\EditCase;
use App\Filament\Resources\FuneralCaseResource\Pages\ViewCase;
use App\Models\FuneralCase;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CaseLivewirePayloadTest extends TestCase
{
    use RefreshDatabase;

    private FuneralCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $file = UploadedFile::fake()->image('composition.jpg', 1000, 700);
        $path = 'manifest-editor/payload-test.jpg';
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
        $layout = ['revision' => (string) Str::uuid(), 'scene' => ['version' => 1, 'elements' => []], 'image_path' => $path, 'assets' => ['photo' => 'data:image/png;base64,'.str_repeat('A', 1250000)], 'source' => ['code' => 'Prova payload']];
        $this->case = FuneralCase::create(['type' => 'cremation', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'manifest_layout' => $layout, 'prelapide_layout' => $layout]);
    }

    public function test_edit_and_view_requests_stay_small_and_can_generate_prelapide_with_large_saved_layouts(): void
    {
        foreach ([EditCase::class, ViewCase::class] as $page) {
            $component = Livewire::test($page, ['record' => $this->case->id])->assertSuccessful();
            $state = $component->get('data');
            $this->assertArrayNotHasKey('manifest_layout', $state);
            $this->assertArrayNotHasKey('prelapide_layout', $state);
            $this->assertLessThan(100 * 1024, strlen(json_encode($component->snapshot)));
            $component->callAction('generate_prelapide')->assertHasNoActionErrors();
        }
        $this->assertSame(2, $this->case->documents()->where('kind', 'prelapide')->count());
    }

    public function test_saving_case_preserves_both_layouts_and_generic_serialization_omits_them(): void
    {
        $before = $this->case->only(['manifest_layout', 'prelapide_layout']);
        $this->assertArrayNotHasKey('manifest_layout', $this->case->toArray());
        $this->assertArrayNotHasKey('prelapide_layout', $this->case->toArray());
        Livewire::test(EditCase::class, ['record' => $this->case->id])->fillForm(['first_name' => 'Nome corretto'])->call('save')->assertHasNoFormErrors();
        $this->assertSame($before, $this->case->fresh()->only(['manifest_layout', 'prelapide_layout']));
        $this->assertSame('Nome corretto', $this->case->fresh()->first_name);
    }
}
