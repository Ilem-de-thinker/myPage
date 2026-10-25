<?php

namespace App\Domains\Analytics\Http\Controllers;

use App\Domains\Analytics\Services\RecordStoreEvent;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StoreEventController extends Controller
{
    public function store(Request $request, StorePage $page, RecordStoreEvent $events): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string', 'in:whatsapp_tap,enquiry,traffic'],
            'product_id' => ['nullable', 'integer'],
            'service_id' => ['nullable', 'integer'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $events->handle($page, $data['event'], $request, $data);

        return response()->json(['recorded' => true], 202);
    }
}
