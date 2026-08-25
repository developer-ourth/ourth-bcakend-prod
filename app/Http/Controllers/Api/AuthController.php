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
                    'kyc_status' => $vendor->kyc_status,
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
            'email' => 'required|string',
            'password' => 'required',
        ]);

        $identifier = trim($request->email);
        $user = null;

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $identifier)->first();
        } else {
            $user = User::where('phone', $identifier)->first();
        }

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
        $kycStatus = $user->role === 'vendor' ? $user->vendor?->kyc_status : null;

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
                    'kyc_status' => $kycStatus,
                ],
            ],
        ]);
    }

    /**
     * Register â€” creates a new user account.
     *
     * POST /api/v1/auth/register
     * { "name": "...", "email": "...", "password": "...", "password_confirmation": "..." }
     */
    public function register(Request $request, \App\Services\VendorService $vendorService)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:15|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'nullable|in:consumer,vendor',
            'business_name' => 'nullable|string|max:255',
            'gstin' => 'nullable|string|max:15|unique:vendors,gstin',
        ]);

        $role = $request->input('role', 'consumer');

        if ($role === 'vendor') {
            $result = $vendorService->register([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone ?? '',
                'password' => $request->password,
                'business_name' => $request->business_name ?? $request->name,
                'gstin' => $request->gstin,
            ]);
            $user = User::find($result['user']['id']);
        } else {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'consumer',
                'email_verified_at' => now(),
            ]);
        }

        $token = $user->createToken('dashboard')->plainTextToken;

        $vendorId = $user->role === 'vendor' ? $user->vendor?->id : null;
        $kycStatus = $user->role === 'vendor' ? $user->vendor?->kyc_status : null;

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
                    'kyc_status' => $kycStatus,
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
        $kycStatus = $u->role === 'vendor' ? $u->vendor?->kyc_status : null;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'vendor_id' => $vendorId,
                'kyc_status' => $kycStatus,
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

    /**
     * Google Login.
     */
    public function google(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        try {
            $client = new \Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
            $payload = $client->verifyIdToken($request->id_token);

            if (!$payload) {
                return response()->json(['success' => false, 'message' => 'Invalid Google token.'], 401);
            }

            $email = $payload['email'];
            $name = $payload['name'] ?? 'Google User';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(Str::random(24)),
                    'role' => 'consumer',
                    'email_verified_at' => now(),
                ]
            );

            // Revoke old tokens
            $user->tokens()->where('name', 'dashboard')->delete();
            $token = $user->createToken('dashboard')->plainTextToken;

            $user->update(['last_login_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Google login successful',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'vendor_id' => $user->role === 'vendor' ? $user->vendor?->id : null,
                        'kyc_status' => $user->role === 'vendor' ? $user->vendor?->kyc_status : null,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Google login failed: ' . $e->getMessage()], 401);
        }
    }

    /**
     * Send Email OTP.
     */
    public function sendEmailOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $otp = (string) rand(100000, 999999);
        $email = $request->email;

        // Store OTP in cache for 5 minutes
        \Illuminate\Support\Facades\Cache::put('otp_' . $email, $otp, now()->addMinutes(5));

        // Send Email via Resend SMTP
        try {
            \Illuminate\Support\Facades\Mail::raw("Your OURTH login OTP is: $otp. This code is valid for 5 minutes.", function ($message) use ($email) {
                $message->to($email)
                        ->subject('Your OURTH Login OTP');
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send OTP to $email. " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send email.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully to your email.',
        ]);
    }

    /**
     * Verify OTP (Email or Firebase Phone Auth).
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string', // phone or email
            'otp' => 'required|string', // email 6-digit OTP OR Firebase idToken for phone
            'type' => 'required|in:email,phone'
        ]);

        $identifier = $request->identifier;
        $type = $request->type;

        if ($type === 'email') {
            $cachedOtp = \Illuminate\Support\Facades\Cache::get('otp_' . $identifier);
            if (!$cachedOtp || $cachedOtp !== $request->otp) {
                throw ValidationException::withMessages([
                    'otp' => ['Invalid or expired OTP.'],
                ]);
            }
            \Illuminate\Support\Facades\Cache::forget('otp_' . $identifier);
        } else {
            // For phone, the OTP field is actually the Firebase ID Token
            $idToken = $request->otp;
            try {
                // Verify Firebase ID Token via Google Identity Toolkit API
                $apiKey = env('FIREBASE_API_KEY'); // Ensure this is set in .env
                if (!$apiKey) {
                    throw new \Exception("FIREBASE_API_KEY not configured.");
                }
                
                $response = \Illuminate\Support\Facades\Http::post("https://identitytoolkit.googleapis.com/v1/accounts:lookup?key={$apiKey}", [
                    'idToken' => $idToken,
                ]);
                
                if (!$response->successful() || empty($response->json()['users'])) {
                    throw new \Exception("Invalid Firebase token.");
                }
                
                $firebaseUser = $response->json()['users'][0];
                $verifiedPhone = $firebaseUser['phoneNumber'] ?? ''; // e.g. +919876543210
                
                $cleanVerified = preg_replace('/\D/', '', $verifiedPhone);
                $cleanIdentifier = preg_replace('/\D/', '', $identifier);

                if (empty($cleanVerified) || ($cleanVerified !== $cleanIdentifier && !str_ends_with($cleanVerified, $cleanIdentifier) && !str_ends_with($cleanIdentifier, $cleanVerified))) {
                    throw new \Exception("Token phone number ({$verifiedPhone}) does not match requested number ({$identifier}).");
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Phone OTP verification failed for identifier '{$identifier}': " . $e->getMessage());
                throw ValidationException::withMessages([
                    'otp' => ['Invalid phone verification. ' . $e->getMessage()],
                ]);
            }
        }

        // Check if user exists
        $user = User::where($type === 'email' ? 'email' : 'phone', $identifier)->first();

        // If user doesn't exist, we don't create one immediately. We return a flag requesting profile completion.
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'OTP verified. Profile completion required.',
                'requires_profile_completion' => true,
                'identifier' => $identifier,
                'type' => $type
            ]);
        }

        // Proceed with login
        $user->tokens()->where('name', 'dashboard')->delete();
        $token = $user->createToken('dashboard')->plainTextToken;
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'requires_profile_completion' => false,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'vendor_id' => $user->role === 'vendor' ? $user->vendor?->id : null,
                    'kyc_status' => $user->role === 'vendor' ? $user->vendor?->kyc_status : null,
                ],
            ],
        ]);
    }
}
