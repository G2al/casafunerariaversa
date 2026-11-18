<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ServiceOrderPhotoController extends Controller
{
    public function __invoke(Request $request, ServiceOrder $order)
    {
        Gate::authorize('viewPhoto', $order);
        $path = $order->photo_path;
        abort_unless($path && str_starts_with($path, 'service-order-photos/') && ! str_contains($path, '..') && ! str_contains($path, '\\') && Storage::disk('local')->exists($path), 404);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        $name = 'ordine-'.$order->id.'.webp';

        return $request->boolean('download')
            ? Storage::disk('local')->download($path, $name, $headers)
            : Storage::disk('local')->response($path, $name, $headers);
    }
}
