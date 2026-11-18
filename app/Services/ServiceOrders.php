<?php

namespace App\Services;

use App\Events\ServiceOrderChanged;
use App\Models\AuditEvent;
use App\Models\FuneralCase;
use App\Models\ServiceOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceOrders
{
    public function save(int $caseId, array $input, ?int $revision, string $action, ?UploadedFile $photo = null, bool $removePhoto = false, ?int $expectedOrderId = null): ServiceOrder
    {
        abort_unless(in_array($action, ['save', 'submit', 'confirm', 'reopen'], true), 422);

        $newPhoto = null;
        try {
            return DB::transaction(function () use ($caseId, $input, $revision, $action, $photo, $removePhoto, $expectedOrderId, &$newPhoto) {
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
                if ($order?->id !== $expectedOrderId || $order?->revision !== $revision) {
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
                    $oldPhoto = $order->photo_path;
                    if ($photo) {
                        Validator::make(['photo' => $photo], ['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240'])->validate();
                        $newPhoto ??= app(OptimizedUpload::class)->store($photo, 'service-order-photos');
                        $order->photo_path = $newPhoto;
                    } elseif ($removePhoto) {
                        $order->photo_path = null;
                    }
                    if ($oldPhoto && $oldPhoto !== $order->photo_path) {
                        DB::afterCommit(fn () => $this->removePhotoFile($oldPhoto));
                    }
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
        } catch (\Throwable $error) {
            if ($newPhoto) {
                $this->removePhotoFile($newPhoto);
            }
            throw $error;
        }
    }

    public function delete(int $orderId, int $revision): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        DB::transaction(function () use ($orderId, $revision) {
            $before = ServiceOrder::find($orderId);
            if (! $before) {
                return;
            }
            $case = $before->funeral_case_id ? FuneralCase::withTrashed()->whereKey($before->funeral_case_id)->lockForUpdate()->first() : null;
            $order = ServiceOrder::whereKey($orderId)->lockForUpdate()->first();
            if (! $order) {
                return;
            }
            Gate::authorize('delete', $order);
            if ($order->revision !== $revision) {
                throw ValidationException::withMessages(['order' => 'L’ordine è cambiato. Ricaricalo prima di eliminarlo.']);
            }
            AuditEvent::create(['user_id' => auth()->id(), 'funeral_case_id' => $case?->id, 'event' => 'service_order_deleted', 'changed_fields' => ['order_id' => $order->id, 'case_code' => $order->case_code, 'status' => $order->status]]);
            $photo = $order->photo_path;
            $order->delete(); // Le revisioni collegate sono eliminate dal vincolo cascade.
            DB::afterCommit(function () use ($photo, $case) {
                $this->removePhotoFile($photo);
                if (config('occupancy.enabled')) {
                    try {
                        event(new ServiceOrderChanged((int) ($case?->operator_id ?? 0)));
                    } catch (\Throwable $error) {
                        report($error);
                    }
                }
            });
        }, 3);
    }

    private function removePhotoFile(?string $path): void
    {
        if (! $path || ! str_starts_with($path, 'service-order-photos/') || str_contains($path, '..') || str_contains($path, '\\')) {
            return;
        }
        try {
            if (! Storage::disk('local')->delete($path)) {
                Log::warning('Rimozione foto ordine non riuscita.', ['path' => $path]);
            }
        } catch (\Throwable $error) {
            report($error);
        }
    }
}
