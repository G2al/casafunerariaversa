<?php

namespace App\Services;

use App\Models\FuneralCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CaseReports
{
    public function query(User $user, array $filters = []): Builder
    {
        $query = FuneralCase::withTrashed()->visibleTo($user);
        $field = $this->dateField($filters);

        return $query->when($filters['from'] ?? null, fn ($q, $value) => $q->whereDate($field, '>=', $value))
            ->when($filters['until'] ?? null, fn ($q, $value) => $q->whereDate($field, '<=', $value));
    }

    public function dateField(array $filters): string
    {
        return in_array($filters['date_field'] ?? '', ['created_at', 'registered_at', 'death_date'], true) ? $filters['date_field'] : 'registered_at';
    }

    public function summary(User $user, array $filters = []): array
    {
        $query = $this->query($user, $filters);
        $row = (clone $query)->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'in_progress' AND deleted_at IS NULL THEN 1 ELSE 0 END) as in_progress, SUM(CASE WHEN status = 'completed' AND deleted_at IS NULL THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = 'archived' OR deleted_at IS NOT NULL THEN 1 ELSE 0 END) as archived, SUM(CASE WHEN type = 'cremation' THEN 1 ELSE 0 END) as cremation, SUM(CASE WHEN type = 'standard_transport' THEN 1 ELSE 0 END) as standard_transport")->first()->getAttributes();
        $row['today'] = (clone $query)->whereDate('created_at', today())->count();
        $row['month'] = (clone $query)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();

        return array_map(fn ($value) => (int) $value, $row);
    }

    public function monthly(User $user, array $filters = [], bool $lastTwelve = true): array
    {
        $field = $this->dateField($filters);
        $expression = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', $field)" : "DATE_FORMAT($field, '%Y-%m')";
        $end = isset($filters['until']) && $filters['until'] ? Carbon::parse($filters['until'])->endOfMonth() : now()->endOfMonth();
        $start = $end->copy()->subMonthsNoOverflow(11)->startOfMonth();
        if (! $lastTwelve && ! empty($filters['from'])) {
            $start = Carbon::parse($filters['from'])->startOfMonth();
        }
        $counts = $this->query($user, $filters)->whereBetween($field, [$start, $end])->selectRaw("$expression as period, COUNT(*) as total")->groupByRaw($expression)->pluck('total', 'period');
        $months = [];
        for ($date = $start->copy(); $date->lte($end); $date->addMonth()) {
            $months[$date->format('Y-m')] = (int) ($counts[$date->format('Y-m')] ?? 0);
        }

        return $months;
    }

    public function operators(User $user, array $filters = []): array
    {
        abort_unless($user->isAdmin(), 403);
        $created = $this->query($user, $filters)->selectRaw('created_by, COUNT(*) as total')->groupBy('created_by')->pluck('total', 'created_by');
        $assigned = $this->query($user, $filters)->selectRaw("operator_id, COUNT(*) as managed, SUM(CASE WHEN status = 'draft' AND deleted_at IS NULL THEN 1 ELSE 0 END) as draft, SUM(CASE WHEN status = 'in_progress' AND deleted_at IS NULL THEN 1 ELSE 0 END) as in_progress, SUM(CASE WHEN status = 'completed' AND deleted_at IS NULL THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = 'archived' OR deleted_at IS NOT NULL THEN 1 ELSE 0 END) as archived, SUM(CASE WHEN type = 'cremation' THEN 1 ELSE 0 END) as cremation, SUM(CASE WHEN type = 'standard_transport' THEN 1 ELSE 0 END) as standard_transport")->groupBy('operator_id')->get()->keyBy('operator_id');

        return User::orderBy('name')->get(['id', 'name'])->map(function ($operator) use ($created, $assigned) {
            $row = $assigned->get($operator->id);

            return ['name' => $operator->name, 'created' => (int) ($created[$operator->id] ?? 0), 'managed' => (int) ($row?->managed ?? 0), 'draft' => (int) ($row?->draft ?? 0), 'in_progress' => (int) ($row?->in_progress ?? 0), 'completed' => (int) ($row?->completed ?? 0), 'archived' => (int) ($row?->archived ?? 0), 'cremation' => (int) ($row?->cremation ?? 0), 'standard_transport' => (int) ($row?->standard_transport ?? 0)];
        })->all();
    }
}
