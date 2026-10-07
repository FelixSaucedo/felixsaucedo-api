<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactSubmissionResource;
use App\Models\ContactSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use App\Enums\SubmissionStatus;

class ContactSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ContactSubmission::class);
        $filters = $request->validate([
            'status' => ['sometimes', Rule::enum(SubmissionStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = ContactSubmission::query()->latest('id');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return ContactSubmissionResource::collection($query->paginate((int) ($filters['per_page'] ?? 25)))
            ->response();
    }

    public function show(ContactSubmission $submission): JsonResponse
    {
        Gate::authorize('view', $submission);

        return (new ContactSubmissionResource($submission))->response();
    }

    public function destroy(ContactSubmission $submission): JsonResponse
    {
        Gate::authorize('delete', $submission);
        $submission->delete();

        return response()->json(null, 204);
    }
}
