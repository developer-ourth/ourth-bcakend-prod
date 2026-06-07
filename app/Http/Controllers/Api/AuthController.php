<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Valid roles that can log in via the dashboard portals */
    private const ALLOWED_ROLES = [
        'founder', 'vendor', 'consumer', 'operations',
        'waste_management', 'finance', 'admin', 'marketing',
    ];

    /**
     * Login with Vendor ID or phone number.
     *
     * POST /api/v1/auth/login-vendor
     * { "identifier": "123456", "password": "..." }   — vendor code
     * { "identifier": "+919876543210", "password": "..." } — phone number
     */
    public function loginWithVendorId(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required',
        ]);

        $identifier = trim($request->identifier);

        // Try vendor_code first (up to 6 digits)
        $vendor = null;
        if (preg_match('/^\d{1,6}$/', $identifier)) {
            $vendor = Vendor::where('vendor_code', $identifier)->first();
        }

        // Fallback: look up by phone number on the user
        if (! $vendor) {
            $user = User::where('phone', $identifier)
                ->where('user_type', 'vendor')
                ->first();
            $vendor = $user?->vendor;
        }

        if (! $vendor) {
            throw ValidationException::withMessages([
                'identifier' => ['No account found with this phone number or Vendor ID.'],
            ]);
        }

        $user = $vendor->user;

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['The provided credentials are invalid.'],
            ]);
        }

        if ($user->user_type !== 'vendor') {
            throw ValidationException::withMessages([
                'identifier' => ['This account is not a vendor account.'],
            ]);
        }

        $user->tokens()->where('name', 'mobile')->delete();
        $token = $user->createToken('mobile')->plainTextToken;
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'vendor_id' => $vendor->id,
                ],
            ],
        ]);
    }

    /**
     * Login — returns a Sanctum token and user info.
     *
     * POST /api/v1/auth/login
     * { "email": "...", "password": "..." }
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        if (! in_array($user->role, self::ALLOWED_ROLES)) {
            throw ValidationException::withMessages([
                'email' => ['This account does not have portal access.'],
            ]);
        }

        // Revoke old tokens for this device name to avoid accumulation
        $user->tokens()->where('name', 'dashboard')->delete();
        $token = $user->createToken('dashboard')->plainTextToken;

        $user->update(['last_login_at' => now()]);

        $vendorId = $user->role === 'vendor' ? $user->vendor?->id : null;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'vendor_id' => $vendorId,
                ],
            ],
        ]);
    }

    /**
     * Register â€” creates a new user account.
     *
     * POST /api/v1/auth/register
     * { "name": "...", "email": "...", "password": "...", "password_confirmation": "...", "role": "..." }
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:'.implode(',', self::ALLOWED_ROLES),
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('dashboard')->plainTextToken;

        $vendorId = $user->role === 'vendor' ? $user->vendor?->id : null;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'vendor_id' => $vendorId,
                ],
            ],
        ], 201);
    }

    /**
     * Get the authenticated user.
     *
     * GET /api/v1/auth/user
     */
    public function user(Request $request)
    {
        $u = $request->user();
        $vendorId = $u->role === 'vendor' ? $u->vendor?->id : null;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'vendor_id' => $vendorId,
            ],
        ]);
    }

    /**     * Get the authenticated vendor's application status.
     *
     * GET /api/v1/auth/vendor-status
     *
     * Maps approval_stage to a simple integer step so the mobile app
     * can light up the progress steps without business-logic coupling:
     *   1 = registered (pending_documents)
     *   2 = under review
     *   3 = approved / verified
     */
    public function vendorStatus(Request $request)
    {
        $user = $request->user();
        $vendor = $user->vendor;

        if (! $vendor) {
            return response()->json([
                'success' => false,
                'message' => 'No vendor profile found for this account.',
            ], 404);
        }

        $approval = $vendor->approval;
        $stage = $approval?->approval_stage ?? 'pending_documents';

        $step = match ($stage) {
            'pending_documents' => 1,
            'under_review' => 2,
            'approved' => 3,
            'rejected' => -1,
            default => 1,
        };

        return response()->json([
            'success' => true,
            'vendor_id' => $vendor->id,
            'approval_stage' => $stage,
            'kyc_status' => $vendor->kyc_status,
            'step' => $step,
        ]);
    }

    /**     * Logout â€” revoke the current Sanctum token.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Send a password reset link to the given email.
     *
     * POST /api/v1/auth/forgot-password
     * { "email": "..." }
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Password reset link sent to your email.']);
    }

    /**
     * Reset the user's password using the token from the email link.
     *
     * POST /api/v1/auth/reset-password
     * { "token": "...", "email": "...", "password": "...", "password_confirmation": "..." }
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])
                    ->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [__($status)],
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Password has been reset successfully.']);
    }
}
