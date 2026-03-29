<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Api\Concerns\InteractsWithGymPermissions;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    use InteractsWithGymPermissions;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer, UserRole::Client);

        $perPage = max(1, min(50, $request->integer('per_page', 15)));
        $notifications = $actor->notifications()->latest()->paginate($perPage);

        return response()->json([
            'data' => $notifications->getCollection()->map(fn (DatabaseNotification $notification) => $this->serializeNotification($notification))->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $actor->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer, UserRole::Client);

        $notification = $actor->notifications()->find($notificationId);
        if (! $notification) {
            abort(404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'data' => $this->serializeNotification($notification->fresh()),
            'meta' => [
                'unread_count' => $actor->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer, UserRole::Client);

        $actor->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notifications marked as read.',
            'meta' => [
                'unread_count' => 0,
            ],
        ]);
    }

    private function serializeNotification(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->data['kind'] ?? 'general',
            'title' => $notification->data['title'] ?? 'Notification',
            'message' => $notification->data['message'] ?? '',
            'action_url' => $notification->data['action_url'] ?? null,
            'scheduled_on' => $notification->data['scheduled_on'] ?? null,
            'program_name' => $notification->data['program_name'] ?? null,
            'client_name' => $notification->data['client_name'] ?? null,
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
        ];
    }
}
