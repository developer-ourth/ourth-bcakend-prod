<?php

namespace App\Http\Controllers\Api\Consumer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NotificationController
 *
 * In-app notifications for the authenticated consumer.
 */
class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user.
     *
     * GET /api/v1/me/notifications
     * Query: unread_only (bool), per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)
            ->select(['id', 'type', 'title', 'message', 'data', 'is_read', 'read_at', 'created_at'])
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $notifications->items(),
            'meta'    => [
                'current_page'  => $notifications->currentPage(),
                'last_page'     => $notifications->lastPage(),
                'total'         => $notifications->total(),
                'unread_count'  => Notification::where('user_id', $request->user()->id)->where('is_read', false)->count(),
            ],
        ]);
    }

    /**
     * Mark a single notification as read.
     *
     * PATCH /api/v1/me/notifications/{notification}/read
     */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403, 'Forbidden.');

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Notification marked as read.']);
    }

    /**
     * Mark all notifications as read.
     *
     * POST /api/v1/me/notifications/mark-all-read
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'All notifications marked as read.']);
    }
}
