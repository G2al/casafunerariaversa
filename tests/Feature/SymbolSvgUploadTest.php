<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\SymbolResource\Pages\ManageSymbols;
use App\Models\FuneralCase;
use App\Models\Symbol;
use App\Models\SymbolFolder;
use App\Models\User;
use App\Services\OptimizedUpload;
use App\Services\PdfGenerator;
use App\Services\PrivateImage;
use App\Services\SafeSvg;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SymbolSvgUploadTest extends TestCase
{
    use RefreshDatabase;

    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 300"><defs><linearGradient id="gold"><stop offset="0" stop-color="#a07a30"/><stop offset="1" stop-color="#174f37"/></linearGradient></defs><path fill="url(#gold)" d="M80 10h40v70h60v40h-60v170H80V120H20V80h60z"/></svg>';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    public function test_single_svg_upload_is_visible_in_private_preview_and_prelapide_pdf(): void
    {
        $folder = SymbolFolder::create(['name' => 'Croci']);
        Livewire::test(ManageSymbols::class, ['folder' => $folder->id])->callAction(CreateAction::class, data: ['title' => 'Croce SVG', 'symbol_folder_id' => $folder->id, 'image_path' => UploadedFile::fake()->createWithContent('croce.svg', self::SVG), 'sort_order' => 0, 'is_active' => true])->assertHasNoActionErrors();
        $symbol = Symbol::sole();
        $this->assertStringEndsWith('.svg', $symbol->image_path);
        $this->assertSame($folder->id, $symbol->symbol_folder_id);
        $uri = app(PrivateImage::class)->dataUri($symbol->image_path);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $this->assertEquals([200, 300], app(PrivateImage::class)->dimensions($uri));
        $this->get(route('private.symbol', $symbol))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertHeader('X-Content-Type-Options', 'nosniff')->assertSee('linearGradient', false);
        $case = FuneralCase::create(['type' => 'cremation', 'first_name' => 'Mario', 'last_name' => 'Rossi']);
        $pdf = app(PdfGenerator::class)->render($case, 'prelapide');
        $this->assertStringStartsWith('%PDF-', $pdf['bytes']);
        $this->assertSame(1, preg_match_all('~/Type\s*/Page\b~', $pdf['bytes']));
        $data = $this->getJson(route('private.manifest-editor.show', [$case, 'kind' => 'prelapide']))->assertOk()->json();
        $this->assertContains('symbol', array_column($data['scene']['elements'], 'asset'));
    }

    public function test_bulk_upload_accepts_svg_together_with_png(): void
    {
        $folder = SymbolFolder::create(['name' => 'Misti']);
        Livewire::test(ManageSymbols::class, ['folder' => $folder->id])->callAction('bulk_upload', data: ['images' => [UploadedFile::fake()->createWithContent('Croce.svg', self::SVG), UploadedFile::fake()->image('Fiore.png')]])->assertHasNoActionErrors();
        $this->assertSame(2, $folder->symbols()->count());
        $paths = $folder->symbols()->pluck('image_path')->all();
        $this->assertSame(1, count(array_filter($paths, fn ($path) => str_ends_with($path, '.svg'))));
        $this->assertSame(1, count(array_filter($paths, fn ($path) => str_ends_with($path, '.webp'))));
    }

    public function test_scripts_external_resources_and_css_fetches_are_removed(): void
    {
        $dirty = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" onload="alert(1)"><script>alert(1)</script><foreignObject><p>bad</p></foreignObject><style>@import "https://example.invalid/font.css";</style><rect width="100" height="100" style="fill:url(file:///etc/passwd)"/><image href="https://example.invalid/private.png"/><use href="/private/cases/1/photo"/></svg>';
        $path = app(OptimizedUpload::class)->store(UploadedFile::fake()->createWithContent('unsafe.svg', $dirty), 'symbols');
        $bytes = Storage::disk('local')->get($path);
        foreach (['onload', '<script', 'foreignObject', 'example.invalid', 'file:', '/private/cases', '@import'] as $text) {
            $this->assertStringNotContainsString($text, $bytes);
        }
        $this->assertStringContainsString('<rect', $bytes);
    }

    public function test_embedded_png_is_preserved_without_allowing_embedded_svg(): void
    {
        $file = UploadedFile::fake()->image('inside.png', 20, 30);
        $png = base64_encode(file_get_contents($file->getRealPath()));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="30"><image width="20" height="30" href="data:image/png;base64,'.$png.'"/></svg>';
        $clean = app(SafeSvg::class)->sanitize($svg);
        $this->assertStringContainsString('data:image/png;base64,', $clean['bytes']);
    }

    public function test_invalid_xml_entities_and_non_svg_payloads_are_rejected(): void
    {
        foreach (['<svg><path></svg>', '<html><script>bad</script></html>', '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'] as $svg) {
            try {
                app(SafeSvg::class)->sanitize($svg);
                $this->fail('SVG non valido accettato.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('upload', $e->errors());
            }
        }
        $this->assertSame([],Storage::disk('local')->allFiles('symbols'));
    }
}
