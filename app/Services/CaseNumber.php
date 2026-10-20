<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CaseNumber
{
    public function next(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            DB::table('case_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
            $sequence = DB::table('case_sequences')->where('year', $year)->lockForUpdate()->first();
            $number = $sequence->last_number + 1;
            DB::table('case_sequences')->where('year', $year)->update(['last_number' => $number]);

            return sprintf('FUN-%d-%04d', $year, $number);
        }, 5);
    }
}
