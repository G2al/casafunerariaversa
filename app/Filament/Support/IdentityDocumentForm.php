<?php

namespace App\Filament\Support;

use App\Enums\IdentityDocumentType;
use App\Models\FuneralCase;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\Rules\Enum;

class IdentityDocumentForm
{
    public static function section(): Section
    {
        return Section::make('Documento del defunto')->description('Estremi condivisi dai due modelli PDF. Fronte e retro sono facoltativi, conservati in privato e scaricabili solo da chi può accedere alla pratica.')->schema([
            Select::make('identity_document_type')->label('Tipologia documento')->options(IdentityDocumentType::class)->rules([new Enum(IdentityDocumentType::class)])->live()
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set) {
                    if (filled($state) && blank($get('cremation_details.identification_method'))) {
                        $set('cremation_details.identification_method', 'document');
                    }
                })
                ->helperText(fn (?FuneralCase $record) => filled($record?->cremation_details['identity_document_type'] ?? null) ? 'Tipologia precedente: '.$record->cremation_details['identity_document_type'].'. Puoi specificarla con una delle quattro opzioni.' : null),
            TextInput::make('cremation_details.identity_document_number')->label('Numero documento')->maxLength(255),
            TextInput::make('cremation_details.identity_document_issuer')->label('Comune / ente di rilascio')->maxLength(255),
            DatePicker::make('cremation_details.identity_document_issued_at')->label('Data di rilascio')->displayFormat('d/m/Y')->maxDate(today()),
            DatePicker::make('cremation_details.identity_document_expires_at')->label('Scadenza documento')->displayFormat('d/m/Y')->afterOrEqual('cremation_details.identity_document_issued_at'),
            self::upload('front', 'Fronte del documento'),
            self::upload('back', 'Retro del documento'),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    private static function upload(string $side, string $label): FileUpload
    {
        return PrivateDocumentUpload::make('identity_'.$side.'_path', $label, 'identity-documents', 'private.identity', $side);
    }
}
