<?php

namespace App\Filament\Support;

use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\ManifestRecord;
use App\Models\ManifestTemplate;
use App\Models\Symbol;
use Filament\Actions\Action;

class FilePresentationActions
{
    public static function make(string $field, ?string $casePart = null): array
    {
        $url = function ($record, bool $download) use ($casePart) {
            $type = match (true) {
                $record instanceof FuneralCase => 'case',$record instanceof ManifestRecord => 'manifest',$record instanceof Symbol => 'symbol',$record instanceof ManifestTemplate => 'template',$record instanceof CompanySettings => 'company',default => null
            };

            return $type ? route('private.file-present', [$type, $record->id, $casePart ?? 'photo', 'download' => $download ? 1 : 0]) : null;
        };
        $present = fn ($record) => $record && filled(data_get($record, $field));

        return [Action::make('download_'.str_replace('.', '_', $field))->label('Scarica')->visible($present)->url(fn ($record) => $url($record, true))->openUrlInNewTab(),
            Action::make('print_'.str_replace('.', '_', $field))->label('Stampa')->visible($present)->url(fn ($record) => $url($record, false))->openUrlInNewTab()];
    }
}
