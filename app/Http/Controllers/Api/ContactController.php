<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Support\ContactProcessor;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request, ContactProcessor $processor, SecurityAudit $audit): JsonResponse
    {
        $contact = $processor->process(
            $request->safe()->only(['name', 'email', 'subject', 'message', 'locale', 'theme']),
            $request->ip(),
            $audit->userAgent($request),
        );

        if ($contact->mail_queued_at === null) {
            return response()->json([
                'success' => true,
                'message' => 'Message saved. Email notifications are pending.',
                'notification_status' => 'pending',
            ], 202);
        }

        return response()->json(['success' => true, 'message' => 'Message processed successfully.'], 201);
    }
}
