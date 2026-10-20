<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class CsvExport
{
    public function cases(Builder $query)
    {
        // Difesa aggiuntiva: il chiamante non può allargare lo scope utente.
        $query->visibleTo(auth()->user());

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Codice', 'Nome', 'Cognome', 'Tipo', 'Stato', 'Data pratica', 'Data decesso'], ';', '"', '');
            foreach ($query->cursor() as $case) {
                fputcsv($stream, array_map($this->cell(...), [$case->code, $case->first_name, $case->last_name, $case->type->getLabel(), $case->status->getLabel(), $case->registered_at->format('d/m/Y H:i'), $case->death_date?->format('d/m/Y')]), ';', '"', '');
            }
            fclose($stream);
        }, 'pratiche-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@\-\t\r\n]/u', $value) ? "'".$value : $value;
    }
}
