<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password yang Anda masukkan salah.',
            ], 401);
        }

        // Remove expired tokens
        $user->tokens()->where('expires_at', '<', now())->delete();

        // Token expires in 1 day (every 24 hours)
        $expiresAt = now()->addDay();
        $token = $user->createToken('hexsupport_api_token', ['*'], $expiresAt);

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $this->formatUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Berhasil logout.',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9]+$/'],
        ], [
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka.',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password baru minimal 6 karakter.',
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'message' => 'Password berhasil diubah.',
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'position' => $user->position ?? 'IT Support Admin',
            'email' => $user->email,
            'phone' => $user->phone ?? '-',
            'created_at' => $user->created_at?->toIso8601String(),
            'joined_label' => $user->created_at ? $user->created_at->translatedFormat('d F Y') : '14 Maret 2022',
            'stats' => [
                'resolved_total' => WorkOrder::where('status', 'done')->count(),
                'open_total' => WorkOrder::where('status', 'open')->count(),
            ],
        ];
    }
}
