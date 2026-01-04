<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\Models\User;

// Optional 2FA packages - suppress IDE warnings if not installed

/** @psalm-suppress UndefinedClass */
if (class_exists('PragmaRX\\Google2FA\\Google2FA')) {
    class_alias('PragmaRX\\Google2FA\\Google2FA', 'Google2FAAlias');
}
if (class_exists('BaconQrCode\\Renderer\\ImageRenderer')) {
    class_alias('BaconQrCode\\Renderer\\ImageRenderer', 'ImageRendererAlias');
    class_alias('BaconQrCode\\Renderer\\Image\\SvgImageBackEnd', 'SvgImageBackEndAlias');
    class_alias('BaconQrCode\\Renderer\\RendererStyle\\RendererStyle', 'RendererStyleAlias');
    class_alias('BaconQrCode\\Writer', 'WriterAlias');
}

/**
 * Two-Factor Authentication Controller
 * 
 * Note: This controller gracefully handles missing 2FA packages.
 * Google2FA and BaconQrCode are optional dependencies.
 * 
 * @psalm-suppress UndefinedClass
 */
class TwoFactorController extends Controller
{
    /** @var \PragmaRX\Google2FA\Google2FA|null */
    protected $google2fa;

    public function __construct()
    {
        // Only initialize if package is installed
        if (class_exists('PragmaRX\\Google2FA\\Google2FA')) {
            /** @psalm-suppress UndefinedClass */
            $this->google2fa = new \PragmaRX\Google2FA\Google2FA();
        }
    }

    /**
     * Enable two-factor authentication
     */
    public function enable(Request $request)
    {
        if (!$this->google2fa) {
            return response()->json([
                'error' => 'Two-factor authentication package not installed'
            ], 500);
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid password',
            ], 401);
        }

        // Generate secret key
        $secret = $this->google2fa->generateSecretKey();

        // Store the secret (not yet confirmed)
        $user->two_factor_secret = encrypt($secret);
        $user->save();

        // Generate QR code
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $qrCode = $this->generateQrCode($qrCodeUrl);

        return response()->json([
            'secret' => $secret,
            'qr_code' => $qrCode,
            'message' => 'Scan the QR code with your authenticator app',
        ]);
    }

    /**
     * Confirm two-factor authentication setup
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is not set up',
            ], 400);
        }

        $secret = decrypt($user->two_factor_secret);

        // Verify the code
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return response()->json([
                'message' => 'Invalid verification code',
            ], 401);
        }

        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();

        // Confirm 2FA
        $user->two_factor_confirmed_at = now();
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication enabled successfully',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Verify two-factor authentication code
     */
    public function verify(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code' => 'required|string',
        ]);

        /** @var User $user */
        $user = User::findOrFail($request->user_id);

        if (!$user->two_factor_secret || !$user->two_factor_confirmed_at) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        $secret = decrypt($user->two_factor_secret);

        // Check if it's a recovery code
        if (strlen($request->code) > 6) {
            return $this->verifyRecoveryCode($user, $request->code);
        }

        // Verify the code
        $valid = $this->google2fa->verifyKey($secret, $request->code, 2); // 2 windows tolerance

        if (!$valid) {
            return response()->json([
                'message' => 'Invalid verification code',
                'valid' => false,
            ], 401);
        }

        // Update last login
        $user->last_login_at = now();
        $user->save();

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Verification successful',
            'valid' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'avatar' => $user->avatar,
                'employee_id' => $user->employee_id,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Disable two-factor authentication
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid password',
            ], 401);
        }

        // Disable 2FA
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully',
        ]);
    }

    /**
     * Get two-factor authentication status
     */
    public function status()
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json([
            'enabled' => !is_null($user->two_factor_confirmed_at),
            'confirmed' => !is_null($user->two_factor_confirmed_at),
        ]);
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid password',
            ], 401);
        }

        if (!$user->two_factor_confirmed_at) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        // Generate new recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->save();

        return response()->json([
            'message' => 'Recovery codes regenerated successfully',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Generate QR code as SVG
     * 
     * @psalm-suppress UndefinedClass
     */
    protected function generateQrCode($url)
    {
        /** @psalm-suppress UndefinedClass */
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        /** @psalm-suppress UndefinedClass */
        $writer = new \BaconQrCode\Writer($renderer);

        return $writer->writeString($url);
    }

    /**
     * Generate recovery codes
     */
    protected function generateRecoveryCodes()
    {
        return Collection::times(8, function () {
            return Str::random(10) . '-' . Str::random(10);
        })->all();
    }

    /**
     * Verify recovery code
     */
    protected function verifyRecoveryCode($user, $code)
    {
        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        if (!in_array($code, $recoveryCodes)) {
            return response()->json([
                'message' => 'Invalid recovery code',
                'valid' => false,
            ], 401);
        }

        // Remove used recovery code
        $recoveryCodes = array_values(array_diff($recoveryCodes, [$code]));
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->save();

        // Update last login
        $user->last_login_at = now();
        $user->save();

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Recovery code verified successfully',
            'valid' => true,
            'remaining_codes' => count($recoveryCodes),
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'avatar' => $user->avatar,
                'employee_id' => $user->employee_id,
            ],
            'token' => $token,
        ]);
    }
}
