<?php

namespace App\Services;

use App\Events\ServiceOrderChanged;
use App\Models\FuneralCase;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceOrders
{
    public function save(int $caseId, array $input, ?int $revision, string $action): ServiceOrder
    {
        abort_unless(in_array($action, ['save', 'submit', 'confirm', 'reopen'], true), 422);

        return DB::transaction(function () use ($caseId, $input, $revision, $action) {
            $case = FuneralCase::withTrashed()->whereKey($caseId)->lockForUpdate()->firstOrFail();
            $order = ServiceOrder::where('funeral_case_id', $case->id)->lockForUpdate()->first();
            $user = auth()->user();
            if ($user->isServiceOrderOperator()) {
                abort_unless($order, 403);
                Gate::authorize('view', $order);
                abort_unless(in_array($action, ['save', 'confirm'], true), 403);
            } else {
                Gate::authorize('update', $case);
            }
            abort_if($action === 'confirm' && ! ($user->isAdmin() || $user->isServiceOrderOperator()), 403);
            abort_if($case->trashed(), 422, 'Ripristina la pratica prima di modificare l’ordine.');
            if ($order?->revision !== $revision) {
                throw ValidationException::withMessages(['order' => 'Ordine modificato da un altro utente. Ricarica la versione aggiornata prima di salvare.']);
            }
            if ($action === 'reopen') {
                abort_unless($user->isAdmin() && $order?->status === 'confirmed', 403);
                $order->status = 'draft';
                $order->confirmed_at = null;
                $order->confirmed_by = null;
                $order->revision++;
            } else {
                abort_if($order?->status === 'confirmed', 422, 'Ordine già confermato. Un amministratore deve riaprirlo prima di modificarlo.');
                abort_if($action === 'confirm' && $order?->status !== 'submitted', 422, 'Conferma disponibile soltanto per un ordine inviato.');
                if (isset($input['items']) && is_array($input['items'])) {
                    abort_if(count($input['items']) > 100, 422);
                    $input['items'] = array_values(array_filter($input['items'], fn ($i) => is_array($i) && ($i['selected'] ?? false)));
                }
                $data = Validator::make($input, [
                    'coffin_name' => 'required|string|max:255', 'coffin_type' => 'required|in:wood,biodegradable,double',
                    'items' => 'present|array|max:100', 'items.*' => 'array:name,selected,quantity',
                    'items.*.name' => 'required|string|max:150', 'items.*.selected' => 'required|boolean',
                    'items.*.quantity' => 'nullable|integer|min:1|max:100000',
                ])->validate();
                $items = array_values(array_filter($data['items'], fn ($i) => $i['selected']));
                if ($action === 'confirm' && collect($items)->contains(fn ($i) => blank($i['quantity']))) {
                    throw ValidationException::withMessages(['order' => 'Inserisci la quantità di ogni elemento selezionato prima di confermare.']);
                }
                $items = array_map(fn ($i) => ['name' => trim($i['name']), 'selected' => true, 'quantity' => filled($i['quantity']) ? (int) $i['quantity'] : null], $items);
                if (count(array_unique(array_map(fn ($i) => mb_strtolower($i['name']), $items))) !== count($items)) {
                    throw ValidationException::withMessages(['order' => 'Ogni elemento deve avere un nome distinto.']);
                }
                $order ??= new ServiceOrder(['funeral_case_id' => $case->id, 'created_by' => $user->id, 'revision' => 0, 'status' => 'draft']);
                $order->fill(['coffin_name' => trim($data['coffin_name']), 'coffin_type' => $data['coffin_type'], 'items' => $items, 'case_code' => $case->code, 'deceased_name' => $case->full_name]);
                $order->revision++;
                if ($action === 'submit') {
                    $order->status = 'submitted';
                    $order->submitted_at = now();
                }
                if ($action === 'confirm') {
                    $order->status = 'confirmed';
                    $order->confirmed_at = now();
                    $order->confirmed_by = $user->id;
                }
            }
            $order->save();
            DB::table('service_order_events')->insert(['service_order_id' => $order->id, 'user_id' => $user->id, 'action' => $action, 'snapshot' => json_encode($order->getAttributes(), JSON_THROW_ON_ERROR), 'created_at' => now()]);
            DB::afterCommit(function () use ($case) {
                if (config('occupancy.enabled')) {
                    try {
                        event(new ServiceOrderChanged((int) $case->operator_id));
                    } catch (\Throwable $error) {
                        report($error);
                    }
                }
            });

            return $order;
        }, 3);
    }
}
