<?php

namespace App\Services;

use App\Models\ServiceOrder;

class ServiceOrderReports
{
    public function data(array $filters): array
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $orders = ServiceOrder::where('status', 'confirmed')->whereNotNull('confirmed_at')
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('confirmed_at', '>=', $v))
            ->when($filters['until'] ?? null, fn ($q, $v) => $q->whereDate('confirmed_at', '<=', $v))
            ->orderBy('confirmed_at')->get();
        $totals = [];
        $models = [];
        foreach ($orders as $order) {
            $key = mb_strtolower(trim($order->coffin_name)).'|'.$order->coffin_type;
            $models[$key] ??= ['name' => $order->coffin_name, 'type' => ServiceOrder::coffinTypes()[$order->coffin_type], 'quantity' => 0];
            $models[$key]['quantity']++;
            foreach ($order->items as $item) {
                $key = mb_strtolower(trim($item['name']));
                $totals[$key] ??= ['name' => $item['name'], 'quantity' => 0];
                $totals[$key]['quantity'] += (int) $item['quantity'];
            }
        }

        $typeTotals = [];
        foreach (ServiceOrder::coffinTypes() as $key => $label) {
            $typeTotals[] = ['name' => $label, 'quantity' => $orders->where('coffin_type', $key)->count()];
        }

        return ['typeTotals' => $typeTotals, 'totalItems' => array_sum(array_column($totals, 'quantity')), 'orders' => $orders, 'models' => array_values($models), 'totals' => array_values($totals), 'totalCoffins' => $orders->count(), 'filters' => $filters];
    }
}
