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
        $perPage = $request->query('per_page', 15);
        $role = $request->query('role', null);
        $search = $request->query('search', null);

        $query = User::select([
            'id',
            'name',
            'email',
            'phone',
            'role',
            'email_verified_at',
            'created_at',
        ]);

        if ($role && in_array($role, ['admin', 'government', 'user'])) {
            $query->where('role', $role);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
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
     * Get single user details (admin only)
     *
     * GET /api/v1/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Update user role (admin only)
     *
     * PATCH /api/v1/users/{user}/role
     *
     * Request:
     * {
     *   "role": "admin|government|user"
     * }
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,government,user',
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
                'admins' => User::where('role', 'admin')->count(),
                'government_officers' => User::where('role', 'government')->count(),
                'regular_users' => User::where('role', 'user')->count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
            ],
        ]);
    }
}
