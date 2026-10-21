<?php

namespace App\Filament\Resources;

use App\Filament\Support\DeletionActions;
use App\Filament\Support\PrivateUpload;
use App\Models\ManifestTemplate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManifestTemplateResource extends Resource
{
    protected static ?string $model = ManifestTemplate::class;

    protected static ?string $modelLabel = 'Cornice manifesto';

    protected static ?string $pluralModelLabel = 'Cornici manifesti';

    protected static string|\UnitEnum|null $navigationGroup = 'Risorse del servizio';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titolo')->maxLength(255)->required(),
            PrivateUpload::make('image_path', 'Cornice A4 orizzontale', 'frames')->required()->helperText('JPEG, PNG o WebP. Rapporto A4 orizzontale consigliato; le proporzioni vengono conservate.'),
            TextInput::make('sort_order')->label('Ordine')->integer()->minValue(0)->default(0)->required(), Toggle::make('is_active')->label('Attiva')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withExists(['cases', 'documents']))->columns([
            ImageColumn::make('preview')->label('Anteprima')->state(fn (ManifestTemplate $record) => route('private.template', $record))->imageWidth(140)->imageHeight(100),
            TextColumn::make('title')->label('Titolo')->searchable()->sortable(), TextColumn::make('sort_order')->label('Ordine')->sortable(), IconColumn::make('is_active')->label('Attiva')->boolean(),
        ])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()->disabled(fn (ManifestTemplate $record) => $record->isUsed())->tooltip('Le cornici già utilizzate si possono disattivare, conservando lo storico.')])
            ->toolbarActions([BulkActionGroup::make([DeletionActions::bulk()])->label('Azioni sui selezionati')])
            ->emptyStateHeading('Carica le prime cornici')->emptyStateDescription('Puoi caricare più immagini insieme. Le cornici di esempio non fanno parte del catalogo definitivo.');
    }

    public static function getPages(): array
    {
        return ['index' => ManifestTemplateResource\Pages\ManageTemplates::route('/')];
    }
}
