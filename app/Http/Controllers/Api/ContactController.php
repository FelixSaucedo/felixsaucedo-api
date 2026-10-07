<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Support\ContactProcessor;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request, ContactProcessor $processor): JsonResponse
    {
        $processor->process(
            $request->safe()->only(['name', 'email', 'subject', 'message']),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['success' => true, 'message' => 'Message processed successfully.'], 201);
    }
}
