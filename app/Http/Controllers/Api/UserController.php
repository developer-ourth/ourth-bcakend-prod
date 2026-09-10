<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * UserController - User Management API
 *
 * Handles user listing, role assignment, and account management.
 * Admin only endpoint for managing system users.
 */
class UserController extends Controller
{
    /**
     * Get list of all users (admin only)
     *
     * GET /api/v1/users
     *
     * Query parameters:
     * - page=1
     * - per_page=15
     * - role=admin|government|user
     * - search=email or name
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 50);
        $role = $request->query('role', null);
        $search = $request->query('search', null);

        $query = User::select([
            'id',
            'name',
            'email',
            'phone',
            'role',
            'status',
            'email_verified_at',
            'created_at',
        ])->withCount('orders');

        if ($role && $role !== 'all') {
            $query->where('role', $role);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Delete a single user (admin only)
     *
     * DELETE /api/v1/users/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account from admin dashboard.',
            ], 403);
        }

        try {
            if ($user->tokens()) {
                $user->tokens()->delete();
            }
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete multiple users in bulk (admin only)
     *
     * POST /api/v1/users/bulk-delete
     * Body: { "user_ids": [1, 2, 3] }
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $currentUserId = $request->user()->id;
        $targetIds = array_filter($validated['user_ids'], fn($id) => (int)$id !== (int)$currentUserId);

        if (empty($targetIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid users selected for deletion.',
            ], 400);
        }

        try {
            $users = User::whereIn('id', $targetIds)->get();
            $count = 0;
            foreach ($users as $user) {
                if ($user->tokens()) {
                    $user->tokens()->delete();
                }
                $user->delete();
                $count++;
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$count} users.",
                'deleted_count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk deletion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single user details (admin only)
     *
     * GET /api/v1/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $user->loadCount('orders'),
        ]);
    }

    /**
     * Update user role (admin only)
     *
     * PATCH /api/v1/users/{user}/role
     *
     * Request:
     * {
     *   "role": "admin|government|user|vendor|consumer|operations|founder"
     * }
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string',
        ]);

        try {
            // Prevent changing own role
            if ($user->id === auth()->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot change your own role',
                ], 403);
            }

            $user->update(['role' => $validated['role']]);

            return response()->json([
                'success' => true,
                'message' => 'User role updated',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user statistics (admin only)
     *
     * GET /api/v1/users/stats
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => User::count(),
                'admins' => User::whereIn('role', ['admin', 'founder', 'operations'])->count(),
                'vendors' => User::whereIn('role', ['vendor', 'b2b', 'hawker'])->count(),
                'consumers' => User::whereIn('role', ['consumer', 'user'])->count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
            ],
        ]);
    }
}
