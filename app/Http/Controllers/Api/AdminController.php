<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);

        $query = User::orderBy('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn (User $u) => $this->formatAdmin($u));

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'phone.regex' => 'Nomor HP hanya boleh berisi angka.',
        ]);

        $admin = User::create($validated);

        return response()->json([
            'message' => "Admin '{$admin->name}' berhasil ditambahkan.",
            'data' => $this->formatAdmin($admin),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $admin = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'phone' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'password' => ['nullable', 'string', 'min:6'],
        ], [
            'phone.regex' => 'Nomor HP hanya boleh berisi angka.',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'position' => $validated['position'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = $validated['password'];
        }

        $admin->update($updateData);

        return response()->json([
            'message' => "Data admin '{$admin->name}' berhasil diperbarui.",
            'data' => $this->formatAdmin($admin->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = User::findOrFail($id);

        if ((int) $request->user()->id === (int) $admin->id) {
            return response()->json([
                'message' => 'Admin yang sedang login tidak dapat menghapus akunnya sendiri.',
            ], 422);
        }

        $name = $admin->name;
        $admin->tokens()->delete();
        $admin->delete();

        return response()->json([
            'message' => "Admin '{$name}' berhasil dihapus.",
        ]);
    }

    private function formatAdmin(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'position' => $user->position ?? 'IT Support Admin',
            'email' => $user->email,
            'phone' => $user->phone ?? '-',
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
