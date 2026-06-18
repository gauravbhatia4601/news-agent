<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterSubscriberAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:active,unsubscribed,all'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $validated['status'] ?? 'active';
        $perPage = (int) ($validated['per_page'] ?? 25);
        $search = $validated['search'] ?? null;

        $query = NewsletterSubscriber::query()->orderByDesc('subscribed_at');

        if ($status === 'active') {
            $query->whereNull('unsubscribed_at');
        } elseif ($status === 'unsubscribed') {
            $query->whereNotNull('unsubscribed_at');
        }

        if ($search) {
            $query->where('email', 'like', "%{$search}%");
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', (int) ($validated['page'] ?? 1));

        $totalActive = NewsletterSubscriber::whereNull('unsubscribed_at')->count();
        $totalUnsubscribed = NewsletterSubscriber::whereNotNull('unsubscribed_at')->count();

        return response()->json([
            'data' => [
                'subscribers' => $paginated->items(),
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'stats' => [
                    'active' => $totalActive,
                    'unsubscribed' => $totalUnsubscribed,
                    'total' => $totalActive + $totalUnsubscribed,
                ],
            ],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $subscriber = NewsletterSubscriber::find($id);

        if (! $subscriber) {
            return response()->json(['message' => 'Subscriber not found'], 404);
        }

        $subscriber->delete();

        return response()->json(['data' => ['message' => 'Subscriber removed.']]);
    }
}