<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Mail\PasswordResetLinkMail;
use App\Models\CustomerProfile;
use App\Models\EcommerceSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\CartService;
use App\Support\I18n;
use App\Support\Localization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $clientRole = Role::query()
            ->where('name', 'cliente')
            ->where('is_active', true)
            ->first();

        if (!$clientRole) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay un rol de cliente activo configurado.',
            ], 500);
        }

        $user = DB::transaction(function () use ($validated, $clientRole) {
            $user = User::create([
                'role_id' => $clientRole->id,
                'name' => $validated['name'],
                'username' => $validated['username'] ?? null,
                'email' => $validated['email'],
                'preferred_locale' => Localization::currentLocale(request()),
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
            ]);

            $user->customerProfile()->create([
                'commercial_name' => $validated['commercial_name'] ?? $validated['name'],
                'whatsapp' => $validated['whatsapp'] ?? null,
                'status' => CustomerProfile::STATUS_ACTIVO,
                'onboarding_status' => CustomerProfile::ONBOARDING_IN_PROGRESS,
            ]);

            return $user;
        });

        $user->load(['role.modules', 'region', 'customerProfile']);
        app(CartService::class)->mergeGuestCartIntoUser($this->guestCartToken($request), $user);
        $user->load(['role.modules', 'region', 'customerProfile']);

        $token = $user->createToken(
            $request->input('device_name', 'react-web')
        )->plainTextToken;

        $modules = $user->role->modules
            ->where('is_active', true)
            ->pluck('name')
            ->values()
            ->toArray();

        return response()->json([
            'ok' => true,
            'message' => I18n::get('auth.registered', locale: Localization::currentLocale($request)),
            'token' => $token,
            'token_type' => 'Bearer',
            'redirect_to' => '/',
            'is_internal' => false,
            'must_change_password' => false,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'preferred_locale' => $user->preferred_locale,
                'must_change_password' => $user->must_change_password,
                'role' => [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'display_name' => $user->role->display_name,
                ],
                'region_id' => $user->region_id,
                'region' => $user->region ? [
                    'id' => $user->region->id,
                    'name' => $user->region->name,
                    'slug' => $user->region->slug,
                ] : null,
                'modules' => $modules,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $login = trim($request->input('login'));
        $password = $request->input('password');

        $user = User::query()
            ->with(['role.modules', 'region'])
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if (!$user->role) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu usuario no tiene un rol asignado.',
            ], 403);
        }

        if (!$user->role->is_active) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu rol está inactivo.',
            ], 403);
        }

        $token = $user->createToken(
            $request->input('device_name', 'react-web')
        )->plainTextToken;

        app(CartService::class)->mergeGuestCartIntoUser($this->guestCartToken($request), $user);
        $user->load(['role.modules', 'region']);

        $modules = $user->role->modules
            ->where('is_active', true)
            ->pluck('name')
            ->values()
            ->toArray();

        $isInternal = $user->role->name !== 'cliente';
        $redirectTo = $isInternal ? '/admin' : '/';

        return response()->json([
            'ok' => true,
            'message' => I18n::get('auth.logged_in', locale: Localization::currentLocale($request)),
            'token' => $token,
            'token_type' => 'Bearer',
            'redirect_to' => $redirectTo,
            'is_internal' => $isInternal,
            'must_change_password' => $user->must_change_password,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'preferred_locale' => $user->preferred_locale,
                'must_change_password' => $user->must_change_password,
                'role' => [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'display_name' => $user->role->display_name,
                ],
                'region_id' => $user->region_id,
                'region' => $user->region ? [
                    'id' => $user->region->id,
                    'name' => $user->region->name,
                    'slug' => $user->region->slug,
                ] : null,
                'modules' => $modules,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['role.modules', 'region']);

        if (!$user->role) {
            return response()->json([
                'ok' => false,
                'message' => 'Tu usuario no tiene un rol asignado.',
            ], 403);
        }

        $modules = $user->role->modules
            ->where('is_active', true)
            ->pluck('name')
            ->values()
            ->toArray();

        $isInternal = $user->role->name !== 'cliente';
        $redirectTo = $isInternal ? '/admin' : '/';

        return response()->json([
            'ok' => true,
            'redirect_to' => $redirectTo,
            'is_internal' => $isInternal,
            'must_change_password' => $user->must_change_password,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'preferred_locale' => $user->preferred_locale,
                'must_change_password' => $user->must_change_password,
                'role' => [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'display_name' => $user->role->display_name,
                ],
                'region_id' => $user->region_id,
                'region' => $user->region ? [
                    'id' => $user->region->id,
                    'name' => $user->region->name,
                    'slug' => $user->region->slug,
                ] : null,
                'modules' => $modules,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'ok' => true,
            'message' => I18n::get('auth.logged_out', locale: Localization::currentLocale($request)),
        ]);
    }

    public function updateLocale(Request $request): JsonResponse
    {
        $supportedLocales = collect(EcommerceSetting::supportedLocales())->pluck('code')->all();

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:' . implode(',', $supportedLocales)],
        ]);

        $request->user()->update([
            'preferred_locale' => $validated['locale'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => I18n::get('auth.locale_updated', locale: $validated['locale']),
            'data' => [
                'preferred_locale' => $validated['locale'],
            ],
        ]);
    }

    protected function guestCartToken(Request $request): ?string
    {
        return $request->input('guest_cart_token') ?: $request->header('X-Guest-Cart-Token');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $token = Password::broker()->createToken($user);
            $resetUrl = rtrim((string) config('services.frontend.url'), '/') . '/reset-password?' . http_build_query([
                'email' => $user->email,
                'token' => $token,
                'locale' => Localization::currentLocale($request),
            ]);

            Mail::to($user->email)->send(new PasswordResetLinkMail($user, $resetUrl, Localization::currentLocale($request)));
        }

        return response()->json([
            'ok' => true,
            'message' => I18n::get('auth.password_reset_link_sent', locale: Localization::currentLocale($request)),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['El token es inválido o expiró.'],
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => I18n::get('auth.password_updated', locale: Localization::currentLocale($request)),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
            'must_change_password' => false,
        ]);

        return response()->json([
            'ok' => true,
            'message' => I18n::get('auth.password_updated', locale: Localization::currentLocale($request)),
        ]);
    }
}
