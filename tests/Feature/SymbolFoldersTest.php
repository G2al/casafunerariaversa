<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\SymbolFolderResource;
use App\Filament\Resources\SymbolFolderResource\Pages\ManageFolders;
use App\Filament\Resources\SymbolResource\Pages\ManageSymbols;
use App\Models\FuneralCase;
use App\Models\Symbol;
use App\Models\SymbolFolder;
use App\Models\User;
use App\Services\ImportSymbols;
use App\Services\ManifestEditor;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SymbolFoldersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    public function test_folders_can_be_created_and_multiple_images_uploaded_inside_them(): void
    {
        Livewire::test(ManageFolders::class)->callAction(CreateAction::class, data: ['name' => 'Religiosi'])->assertHasNoActionErrors();
        $folder = SymbolFolder::sole();
        Livewire::test(ManageSymbols::class, ['folder' => $folder->id])->callAction('bulk_upload', data: ['images' => [UploadedFile::fake()->image('Croce.png', 90, 120), UploadedFile::fake()->image('Madonna.jpg', 100, 130)]])->assertHasNoActionErrors();
        $this->assertSame(2, $folder->symbols()->count());
        foreach ($folder->symbols as $symbol) {
            $this->assertSame($folder->id, $symbol->symbol_folder_id);
            $this->assertStringEndsWith('.webp', $symbol->image_path);
            Storage::disk('local')->assertExists($symbol->image_path);
        }
        $this->assertEqualsCanonicalizing(['Croce', 'Madonna'], $folder->symbols->pluck('title')->all());
        Livewire::test(ManageSymbols::class)->assertRedirect(SymbolFolderResource::getUrl());
    }

    public function test_folder_lists_are_scoped_and_symbols_can_be_moved(): void
    {
        $a = SymbolFolder::create(['name' => 'A']);
        $b = SymbolFolder::create(['name' => 'B']);
        $file = UploadedFile::fake()->image('test.png');
        Storage::disk('local')->put('symbols/test.png', file_get_contents($file->getRealPath()));
        $first = Symbol::create(['title' => 'Primo', 'image_path' => 'symbols/test.png', 'symbol_folder_id' => $a->id]);
        $second = Symbol::create(['title' => 'Secondo', 'image_path' => 'symbols/test.png', 'symbol_folder_id' => $b->id]);
        Livewire::test(ManageSymbols::class, ['folder' => $a->id])->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second])->callAction(TestAction::make('edit')->table($first), data: ['symbol_folder_id' => $b->id])->assertHasNoActionErrors();
        $this->assertSame($b->id, $first->fresh()->symbol_folder_id);
        Livewire::test(ManageFolders::class)->assertActionDisabled(TestAction::make('delete')->table($b))->selectTableRecords([$a, $b])->callAction(TestAction::make('delete')->table()->bulk());
        $this->assertModelMissing($a);
        $this->assertModelExists($b);
        $this->assertSame(2, $b->symbols()->count());
    }

    public function test_operators_use_folder_catalog_but_cannot_manage_folders_or_upload_symbols(): void
    {
        $folder = SymbolFolder::create(['name' => 'Fiori']);
        $file = UploadedFile::fake()->image('test.png');
        Storage::disk('local')->put('symbols/test.png', file_get_contents($file->getRealPath()));
        $symbol = Symbol::create(['title' => 'Rosa', 'image_path' => 'symbols/test.png', 'symbol_folder_id' => $folder->id]);
        $this->actingAs(User::factory()->create());
        $case = FuneralCase::create(['type' => 'cremation']);
        $catalog = app(ManifestEditor::class)->catalog($case);
        $this->assertSame($folder->id, $catalog['symbols'][0]['folder']);
        Livewire::test(ManageFolders::class)->assertForbidden();
        Livewire::test(ManageSymbols::class, ['folder' => $folder->id])->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(ImportSymbols::class)->import($folder, ['symbols/test.png']);
    }

    public function test_existing_symbols_are_assigned_to_generali_without_changing_their_images(): void
    {
        $migration = require database_path('migrations/2026_11_04_000200_add_symbol_folders.php');
        $migration->down();
        $id = DB::table('symbols')->insertGetId(['title' => 'Simbolo esistente', 'image_path' => 'symbols/originale.webp', 'sort_order' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $migration->up();
        $symbol = Symbol::findOrFail($id);
        $this->assertSame('Generali', $symbol->folder->name);
        $this->assertSame('symbols/originale.webp', $symbol->image_path);
        $this->assertSame(7,$symbol->sort_order);
    }
}
