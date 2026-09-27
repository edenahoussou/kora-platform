<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class PassengerNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $perPage = min(50, max(1, (int) $request->integer('per_page', 20)));
        $query = $user->notifications()->where('audience', 'passenger')->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))->latest();
        $page = $query->paginate($perPage);
        $unread = $user->notifications()->where('audience', 'passenger')->whereNull('read_at')->count();

        return response()->json([
            'data' => collect($page->items())->map(fn ($notification): array => $this->serialize($notification))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'unread_count' => $unread],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => ['unread' => $user->notifications()->where('audience', 'passenger')->whereNull('read_at')->count()]]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $record = $user->notifications()->where('audience', 'passenger')->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['data' => $this->serialize($record->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updated = $user->notifications()->where('audience', 'passenger')->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['updated' => $updated, 'unread' => 0]]);
    }

    /** @return array<string, mixed> */
    private function serialize(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'uuid' => (string) $notification->getKey(),
            'type' => (string) ($data['type'] ?? 'bus_update'),
            'title' => (string) ($data['title'] ?? 'Mise à jour de votre trajet'),
            'message' => (string) ($data['message'] ?? ''),
            'resource_uuid' => $data['resource_uuid'] ?? null,
            'is_read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
            'data' => $data['data'] ?? [],
        ];
    }
}
