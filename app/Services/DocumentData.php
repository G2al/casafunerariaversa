<?php

namespace App\Services;

use App\Models\CompanySettings;
use App\Models\FuneralCase;
use Illuminate\Validation\ValidationException;

class DocumentData
{
    public function missing(FuneralCase $case, string $kind): array
    {
        $fields = ['first_name' => 'Nome', 'last_name' => 'Cognome', 'death_date' => 'Data del decesso'];
        if ($kind === 'manifest') {
            $fields += ['sex' => 'Sesso', 'manifest_template_id' => 'Cornice'];
        } else {
            $fields += ['birth_date' => 'Data di nascita', 'birth_place' => 'Luogo di nascita', 'death_place' => 'Luogo del decesso', 'transport_at' => 'Data e ora trasporto', 'transport_destination' => 'Destinazione trasporto'];
            $fields['crematorium'] = 'Crematorio';
        }
        $missing = array_values(array_filter($fields, fn ($label, $field) => blank($case->$field), ARRAY_FILTER_USE_BOTH));
        if ($kind === 'document') {
            $missing = array_merge($missing, app(FuneralPacketData::class)->missing($case));
        }

        return $missing;
    }

    public function assemble(FuneralCase $case, string $kind): array
    {
        $missing = $kind === 'document' ? [] : $this->missing($case, $kind);
        if ($kind !== 'document' && $missing) {
            throw ValidationException::withMessages(['document' => 'Dati mancanti: '.implode(', ', $missing).'. Salva la pratica dopo averli compilati.']);
        }
        $company = CompanySettings::current();
        $images = app(PrivateImage::class);
        $data = ['code' => $case->code, 'name' => $case->full_name, 'age' => $case->age, 'type' => $case->type->value, 'type_label' => $case->type->getLabel(), 'company' => $company, 'generated_at' => now()->format('d/m/Y H:i'), 'logo' => $images->dataUri($company['logo_path'] ?? null)];
        if ($kind === 'document') {
            $data['company'] = $company = array_replace(array_fill_keys([
                'legal_name', 'company_city', 'company_address', 'company_authorization_protocol', 'company_authorization_issuer',
                'chamber_city', 'rea_number', 'tax_number', 'practice_license_protocol', 'practice_license_issuer',
                'retail_authorization_protocol', 'retail_authorization_issuer', 'inps_position', 'inail_position',
            ], null), $company);
            $data['logo'] ??= 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('images/logo-casafuneraria.png')));
            $data['request'] = app(FuneralPacketData::class)->assemble($case, $company);

            return $data;
        }
        $case->loadMissing('manifestTemplate');
        if (! $case->manifestTemplate?->is_active) {
            throw ValidationException::withMessages(['manifest_template_id' => 'Seleziona una cornice attiva.']);
        }
        $options = $case->manifest_options ?? [];
        $female = $case->sex === 'female';
        $intro = filled($options['intro'] ?? null) ? $options['intro'] : ($female ? ($company['intro_female'] ?: 'È venuta a mancare all’affetto dei suoi cari la Signora') : ($company['intro_male'] ?: 'È venuto a mancare all’affetto dei suoi cari il Signor'));
        $family = $options['family_text'] ?? '';
        if (! $family) {
            $names = collect($case->family_members ?? [])->map(fn ($member) => trim(($member['relationship'] ?? '').' '.($member['name'] ?? '')))->filter()->implode(', ');
            $family = $names ? 'Ne danno il triste annuncio: '.$names.'.' : '';
        }
        $viewing = ($options['show_viewing'] ?? true) && $case->viewing_address
            ? 'La camera ardente è allestita presso '.trim($case->viewing_venue.' — '.$case->viewing_address).($case->viewing_starts_at ? ', dal '.$case->viewing_starts_at->translatedFormat('d F Y').' alle '.$case->viewing_starts_at->format('H:i') : '').($case->viewing_ends_at ? ' fino al '.$case->viewing_ends_at->translatedFormat('d F Y').' alle '.$case->viewing_ends_at->format('H:i') : '').'.' : '';
        $ceremony = ($options['show_ceremony'] ?? true) && ($case->church || $case->ceremony_at)
            ? 'Il rito funebre si terrà '.($case->ceremony_at ? 'il '.$case->ceremony_at->translatedFormat('d F Y').' alle '.$case->ceremony_at->format('H:i').' ' : '').trim('presso '.$case->church.' '.$case->ceremony_city).'.' : '';
        $transfer = ($options['show_transfer'] ?? true) && $case->transfer_place
            ? 'Il trasferimento avverrà presso '.$case->transfer_place.($case->transfer_at ? ' il '.$case->transfer_at->translatedFormat('d F Y').' alle '.$case->transfer_at->format('H:i') : '').'.' : '';
        $data += [
            'intro' => $intro, 'family_text' => $family, 'viewing' => $viewing, 'ceremony' => $ceremony, 'transfer' => $transfer,
            'closing' => ($options['show_closing'] ?? true) ? ($company['closing_text'] ?? '') : '',
            'place_date' => trim(($options['place'] ?? $case->ceremony_city ?? '').' · '.now()->translatedFormat('d F Y'), ' ·'),
            'photo' => ($options['show_photo'] ?? true) ? $images->dataUri($case->photo_path) : null,
            'background' => $images->dataUri($case->manifestTemplate->image_path),
            'template_title' => $case->manifestTemplate->title,
        ];

        return $data;
    }
}
