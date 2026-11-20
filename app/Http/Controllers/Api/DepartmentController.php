<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);
        $all = $request->boolean('all', false);

        $query = Department::withCount('workOrders')->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sub_departments', 'like', "%{$search}%");
            });
        }

        if ($all) {
            $items = $query->get()->map(fn (Department $d) => $this->formatDepartment($d));
            return response()->json([
                'data' => $items,
            ]);
        }

        $paginator = $query->paginate($perPage);
        $items = collect($paginator->items())->map(fn (Department $d) => $this->formatDepartment($d));

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
            'sub_departments' => ['nullable'],
        ]);

        $subs = $this->normalizeSubDepartments($validated['sub_departments'] ?? []);

        $dept = Department::create([
            'name' => trim($validated['name']),
            'sub_departments' => $subs,
        ]);

        return response()->json([
            'message' => "Department '{$dept->name}' berhasil ditambahkan.",
            'data' => $this->formatDepartment($dept),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sub_departments' => ['nullable'],
        ]);

        $subs = $this->normalizeSubDepartments($validated['sub_departments'] ?? []);

        $dept->update([
            'name' => trim($validated['name']),
            'sub_departments' => $subs,
        ]);

        // Keep department_name in sync for linked work orders
        $dept->workOrders()->update([
            'department_name' => $dept->name,
        ]);

        return response()->json([
            'message' => "Department '{$dept->name}' berhasil diperbarui.",
            'data' => $this->formatDepartment($dept->fresh()->loadCount('workOrders')),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);
        $name = $dept->name;

        // Soft delete so old work orders related to this department do not error
        $dept->delete();

        return response()->json([
            'message' => "Department '{$name}' berhasil dihapus (Soft Delete).",
        ]);
    }

    private function normalizeSubDepartments(mixed $input): array
    {
        if (is_string($input)) {
            $parts = explode(',', $input);
        } elseif (is_array($input)) {
            $parts = $input;
        } else {
            $parts = [];
        }

        $cleaned = [];
        foreach ($parts as $part) {
            $trimmed = trim((string) $part);
            if ($trimmed !== '') {
                $cleaned[] = $trimmed;
            }
        }

        return array_values(array_unique($cleaned));
    }

    private function formatDepartment(Department $dept): array
    {
        $subs = is_array($dept->sub_departments) ? $dept->sub_departments : [];

        return [
            'id' => $dept->id,
            'name' => $dept->name,
            'sub_departments' => $subs,
            'sub_departments_text' => implode(', ', $subs),
            'work_orders_count' => $dept->work_orders_count ?? 0,
            'created_at' => $dept->created_at?->toIso8601String(),
        ];
    }
}
