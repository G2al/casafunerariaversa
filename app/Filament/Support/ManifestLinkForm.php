<?php

namespace App\Filament\Support;

use App\Models\FuneralCase;
use App\Models\ManifestRecord;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;

class ManifestLinkForm
{
    public static function section(): Section
    {
        return Section::make('Collega un manifesto esistente')->description('Se il manifesto è già stato preparato senza pratica, puoi selezionarlo qui. Verifica nominativo e date per distinguere gli omonimi. Nessun collegamento avviene automaticamente.')
            ->schema([View::make('filament.forms.manifest-match-notice'), Select::make('manifest_record_id')->label('Vuoi collegare un manifesto già esistente?')->searchable()->options(fn (?FuneralCase $record) => ManifestRecord::visibleTo(auth()->user())->where(fn ($q) => $q->whereNull('source_case_id')->when($record?->manifest_record_id, fn ($q) => $q->orWhere('id', $record->manifest_record_id)))->latest()->limit(100)->get()->mapWithKeys(fn ($m) => [$m->id => $m->code.' · '.$m->full_name]))
                ->getSearchResultsUsing(fn (string $search) => ManifestRecord::visibleTo(auth()->user())->whereNull('source_case_id')->where(fn ($q) => $q->where('data->first_name', 'like', '%'.$search.'%')->orWhere('data->last_name', 'like', '%'.$search.'%'))->limit(50)->get()->mapWithKeys(fn ($m) => [$m->id => $m->code.' · '.$m->full_name]))
                ->getOptionLabelUsing(fn ($value) => ManifestRecord::visibleTo(auth()->user())->find($value)?->full_name)->nullable()->helperText('Lascia vuoto per preparare un nuovo manifesto dal pulsante dedicato dopo il salvataggio.')]);
    }
}
