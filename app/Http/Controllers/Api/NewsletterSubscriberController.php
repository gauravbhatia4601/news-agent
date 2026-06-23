<?php

namespace App\Http\Controllers\Api;

use App\Models\NewsletterSubscriber;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterSubscriberController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        $subscriber = NewsletterSubscriber::where('email', $validated['email'])->first();

        if ($subscriber) {
            if ($subscriber->unsubscribed_at) {
                $subscriber->update([
                    'unsubscribed_at' => null,
                    'subscribed_at' => now(),
                    'source' => $validated['source'] ?? $subscriber->source,
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr($request->userAgent() ?? '', 0, 255),
                    'consent_text' => 'User re-subscribed via website form',
                    'consent_at' => now(),
                ]);
            }

            return response()->json([
                'data' => ['message' => 'You are already subscribed.', 'email' => $subscriber->email],
            ], 200);
        }

        $subscriber = NewsletterSubscriber::create([
            'email' => $validated['email'],
            'source' => $validated['source'] ?? 'website',
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent() ?? '', 0, 255),
            'consent_text' => 'User subscribed via website form',
            'consent_at' => now(),
            'subscribed_at' => now(),
        ]);

        return response()->json([
            'data' => ['message' => 'Subscribed successfully.', 'email' => $subscriber->email],
        ], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $subscriber = NewsletterSubscriber::where('email', $validated['email'])->first();

        if (! $subscriber) {
            return response()->json(['message' => 'Subscriber not found'], 404);
        }

        $subscriber->update(['unsubscribed_at' => now()]);

        return response()->json(['data' => ['message' => 'Unsubscribed successfully.']]);
    }
}