<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @group Passager — Notifications
 *
 * Inbox BUS du passager authentifié. Les notifications sont isolées par compte.
 */
class PassengerNotificationController extends Controller
{
    /**
     * Liste l’inbox passager, de la plus récente à la plus ancienne.
     *
     * @authenticated
     *
     * @queryParam unread boolean Ne retourner que les notifications non lues. Example: true
     * @queryParam per_page integer Nombre d’éléments par page, de 1 à 50 (20 par défaut). Example: 20
     * @queryParam page integer Numéro de page. Example: 1
     *
     * @response 200 scenario="Inbox passager" {"data":[{"uuid":"a3091549-9179-454e-8440-58179f2208df","type":"booking_confirmed","title":"Réservation confirmée","message":"Votre réservation est confirmée.","resource_uuid":"b4102650-a280-4655-9551-69280321aaef","is_read":false,"read_at":null,"created_at":"2026-09-27T10:00:00.000Z","data":[]}],"meta":{"current_page":1,"last_page":1,"per_page":20,"total":1,"unread_count":1}}
     * @response 401 scenario="Non authentifié" {"message":"Unauthenticated."}
     */
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

    /**
     * Retourne le nombre de notifications passager non lues.
     *
     * @authenticated
     *
     * @response 200 scenario="Compteur non lu" {"data":{"unread":2}}
     * @response 401 scenario="Non authentifié" {"message":"Unauthenticated."}
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => ['unread' => $user->notifications()->where('audience', 'passenger')->whereNull('read_at')->count()]]);
    }

    /**
     * Marque comme lue une notification de l’utilisateur authentifié.
     *
     * @authenticated
     *
     * @urlParam notification string required UUID de la notification. Example: a3091549-9179-454e-8440-58179f2208df
     *
     * @response 200 scenario="Notification lue" {"data":{"uuid":"a3091549-9179-454e-8440-58179f2208df","type":"booking_confirmed","title":"Réservation confirmée","message":"Votre réservation est confirmée.","resource_uuid":null,"is_read":true,"read_at":"2026-09-27T10:05:00.000Z","created_at":"2026-09-27T10:00:00.000Z","data":[]}}
     * @response 404 scenario="Notification absente de cette inbox" {"message":"Not found."}
     */
    public function markRead(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $record = $user->notifications()->where('audience', 'passenger')->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['data' => $this->serialize($record->fresh())]);
    }

    /**
     * Marque toutes les notifications passager non lues comme lues.
     *
     * @authenticated
     *
     * @response 200 scenario="Inbox marquée comme lue" {"data":{"updated":2,"unread":0}}
     */
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
