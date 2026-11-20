<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WorkOrderController extends Controller
{
    /**
     * Parse pasted raw text and check department auto-mapping without saving yet.
     */
    public function parsePreview(Request $request): JsonResponse
    {
        $rawText = (string) $request->input('raw_text', '');
        $parsed = $this->parseRawWorkOrderText($rawText);

        $matchedDept = null;
        if ($parsed['raw_department'] !== '') {
            $matchedDept = Department::matchBySubDepartment($parsed['raw_department']);
        }

        $dateObj = $this->parseFlexibleDate($parsed['date_requested_raw']);
        $deadlineObj = $dateObj ? $dateObj->copy()->addDays(2) : null;

        return response()->json([
            'parsed' => [
                'raw_department' => $parsed['raw_department'],
                'requested_by' => $parsed['requested_by'],
                'date_requested_raw' => $parsed['date_requested_raw'],
                'date_requested' => $dateObj?->format('Y-m-d'),
                'date_requested_label' => $dateObj?->format('d F Y'),
                'completion_deadline' => $deadlineObj?->format('Y-m-d'),
                'completion_deadline_label' => $deadlineObj?->format('d F Y'),
                'description' => $parsed['description'],
                'location' => $parsed['location'],
            ],
            'matched_department' => $matchedDept ? [
                'id' => $matchedDept->id,
                'name' => $matchedDept->name,
                'sub_departments' => $matchedDept->sub_departments ?? [],
            ] : null,
            'requires_manual_department' => $parsed['raw_department'] !== '' && $matchedDept === null,
            'next_work_order_no' => WorkOrder::generateNextWorkOrderNo(),
            'available_departments' => Department::orderBy('name')->get(['id', 'name', 'sub_departments']),
        ]);
    }

    /**
     * Store a new Work Order from Quick Paste (or structured fields).
     */
    public function store(Request $request): JsonResponse
    {
        $rawText = trim((string) $request->input('raw_text', ''));

        if ($rawText !== '') {
            $parsed = $this->parseRawWorkOrderText($rawText);
            $rawDept = $parsed['raw_department'];
            $requestedBy = $parsed['requested_by'];
            $dateRequestedRaw = $parsed['date_requested_raw'];
            $description = $parsed['description'];
            $location = $parsed['location'];
        } else {
            $rawDept = trim((string) $request->input('raw_department', $request->input('department', '')));
            $requestedBy = trim((string) $request->input('requested_by', ''));
            $dateRequestedRaw = trim((string) $request->input('date_requested', ''));
            $description = trim((string) $request->input('description', ''));
            $location = trim((string) $request->input('location', ''));
        }

        $missingFields = [];
        if ($rawDept === '') {
            $missingFields[] = 'Department';
        }
        if ($requestedBy === '') {
            $missingFields[] = 'Requested By';
        }
        if ($dateRequestedRaw === '') {
            $missingFields[] = 'Date Requested';
        }
        if ($description === '') {
            $missingFields[] = 'Description of Work Requested';
        }
        if ($location === '') {
            $missingFields[] = 'Location';
        }

        if (! empty($missingFields)) {
            return response()->json([
                'message' => 'Format teks belum lengkap. Field yang belum ditemukan: ' . implode(', ', $missingFields),
                'missing_fields' => $missingFields,
            ], 422);
        }

        $dateRequested = $this->parseFlexibleDate($dateRequestedRaw);
        if (! $dateRequested) {
            return response()->json([
                'message' => "Format tanggal Date Requested ('{$dateRequestedRaw}') tidak valid. Contoh: 18/11/2026 atau 19 November 2026.",
            ], 422);
        }

        // Auto-Mapping Department against sub_departments
        $matchedDept = Department::matchBySubDepartment($rawDept);
        $manualDepartmentId = $request->input('manual_department_id');
        $manualDepartmentName = trim((string) $request->input('manual_department_name', ''));

        $departmentId = null;
        $departmentName = null;

        if ($matchedDept) {
            $departmentId = $matchedDept->id;
            $departmentName = $matchedDept->name;
        } elseif ($manualDepartmentId) {
            $manualDept = Department::find($manualDepartmentId);
            if ($manualDept) {
                $departmentId = $manualDept->id;
                $departmentName = $manualDept->name;
            }
        } elseif ($manualDepartmentName !== '') {
            $departmentName = $manualDepartmentName;
        }

        if (! $departmentName) {
            return response()->json([
                'message' => "Sub-department '{$rawDept}' tidak ditemukan pada daftar Department. Silakan pilih Department secara manual.",
                'requires_manual_department' => true,
                'raw_department' => $rawDept,
                'available_departments' => Department::orderBy('name')->get(['id', 'name', 'sub_departments']),
            ], 422);
        }

        $workOrderNo = WorkOrder::generateNextWorkOrderNo();
        $completionDeadline = $dateRequested->copy()->addDays(2);
        $initialStatus = $dateRequested->lt(Carbon::today()) ? 'wo_issued' : 'open';

        $wo = WorkOrder::create([
            'work_order_no' => $workOrderNo,
            'department_id' => $departmentId,
            'department_name' => $departmentName,
            'raw_department' => $rawDept,
            'requested_by' => $requestedBy,
            'date_requested' => $dateRequested->format('Y-m-d'),
            'completion_deadline' => $completionDeadline->format('Y-m-d'),
            'location' => $location,
            'description' => $description,
            'status' => $initialStatus,
        ]);

        return response()->json([
            'message' => "Work Order {$wo->work_order_no} berhasil dibuat.",
            'data' => PublicTicketController::formatWorkOrder($wo),
        ], 201);
    }

    /**
     * Mark Work Order as Done with Duration and Solution.
     */
    public function markDone(Request $request, int $id): JsonResponse
    {
        $wo = WorkOrder::findOrFail($id);

        $validated = $request->validate([
            'completed_date' => ['nullable', 'date'],
            'duration' => ['required', 'string', 'max:255'],
            'solution' => ['required', 'string'],
        ], [
            'duration.required' => 'Durasi / waktu pengerjaan wajib diisi.',
            'solution.required' => 'Solusi yang dilakukan wajib diisi.',
        ]);

        $completedAt = ! empty($validated['completed_date'])
            ? Carbon::parse($validated['completed_date'])
            : now();

        $wo->update([
            'status' => 'done',
            'duration' => $validated['duration'],
            'solution' => $validated['solution'],
            'completed_at' => $completedAt,
        ]);

        return response()->json([
            'message' => "Status tiket {$wo->work_order_no} berhasil diubah menjadi Done.",
            'data' => PublicTicketController::formatWorkOrder($wo->fresh()),
        ]);
    }

    /**
     * Update all fields of a Work Order (including work_order_no).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $wo = WorkOrder::findOrFail($id);

        if ($request->filled('work_order_no')) {
            $request->merge([
                'work_order_no' => WorkOrder::normalizeWorkOrderNo((string) $request->input('work_order_no')),
            ]);
        }

        $validated = $request->validate([
            'work_order_no' => ['sometimes', 'required', 'string', 'max:50', 'unique:work_orders,work_order_no,' . $wo->id],
            'requested_by' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'raw_department' => ['nullable', 'string', 'max:255'],
            'date_requested' => ['required', 'date'],
            'completion_deadline' => ['nullable', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'status' => ['required', 'in:open,wo_issued,done'],
            'completed_date' => ['nullable', 'date'],
            'duration' => ['nullable', 'string', 'max:255'],
            'solution' => ['nullable', 'string'],
        ], [
            'work_order_no.unique' => 'Nomor tiket (Work Order No) tersebut sudah digunakan oleh tiket lain.',
        ]);

        $deptId = $validated['department_id'] ?? $wo->department_id;
        $deptName = $validated['department_name'] ?? $wo->department_name;

        if ($deptId) {
            $dept = Department::withTrashed()->find($deptId);
            if ($dept) {
                $deptName = $dept->name;
            }
        }

        $dateRequested = Carbon::parse($validated['date_requested']);
        $deadline = ! empty($validated['completion_deadline'])
            ? Carbon::parse($validated['completion_deadline'])
            : $dateRequested->copy()->addDays(2);

        $wo->update([
            'work_order_no' => $validated['work_order_no'] ?? $wo->work_order_no,
            'requested_by' => $validated['requested_by'],
            'department_id' => $deptId,
            'department_name' => $deptName ?: 'General',
            'raw_department' => $validated['raw_department'] ?? $wo->raw_department ?? $deptName,
            'date_requested' => $dateRequested->format('Y-m-d'),
            'completion_deadline' => $deadline->format('Y-m-d'),
            'location' => $validated['location'],
            'description' => $validated['description'],
            'status' => $validated['status'],
            'duration' => $validated['duration'] ?? $wo->duration,
            'solution' => $validated['solution'] ?? $wo->solution,
            'completed_at' => $validated['status'] === 'done'
                ? (! empty($validated['completed_date']) ? Carbon::parse($validated['completed_date']) : ($wo->completed_at ?? now()))
                : null,
        ]);

        return response()->json([
            'message' => "Data tiket {$wo->work_order_no} berhasil diperbarui.",
            'data' => PublicTicketController::formatWorkOrder($wo->fresh()),
        ]);
    }

    /**
     * Delete a Work Order.
     */
    public function destroy(int $id): JsonResponse
    {
        $wo = WorkOrder::findOrFail($id);
        $no = $wo->work_order_no;
        $wo->delete();

        return response()->json([
            'message' => "Work Order {$no} berhasil dihapus.",
        ]);
    }

    /**
     * Export Work Orders matching active search/filter criteria.
     */
    public function export(Request $request): JsonResponse
    {
        WorkOrder::syncOverdueStatuses();

        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        $query = WorkOrder::with('department')
            ->orderByDesc('date_requested')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('work_order_no', 'like', "%{$search}%")
                    ->orWhere('requested_by', 'like', "%{$search}%")
                    ->orWhere('department_name', 'like', "%{$search}%")
                    ->orWhere('raw_department', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('solution', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['open', 'wo_issued', 'done'], true)) {
            $query->where('status', $status);
        }

        if ($dateFrom !== '') {
            $query->whereDate('date_requested', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('date_requested', '<=', $dateTo);
        }

        $items = $query->get()->map(fn (WorkOrder $wo) => PublicTicketController::formatWorkOrder($wo));

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * Parse key: value lines from pasted text (case-insensitive keys, value after first ':').
     */
    private function parseRawWorkOrderText(string $rawText): array
    {
        $result = [
            'raw_department' => '',
            'requested_by' => '',
            'date_requested_raw' => '',
            'description' => '',
            'location' => '',
        ];

        $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || ! str_contains($trimmed, ':')) {
                continue;
            }

            $colonPos = strpos($trimmed, ':');
            $key = mb_strtolower(trim(substr($trimmed, 0, $colonPos)));
            $value = trim(substr($trimmed, $colonPos + 1));

            if (in_array($key, ['department', 'dept', 'departemen'], true)) {
                $result['raw_department'] = $value;
            } elseif (in_array($key, ['requested by', 'requested_by', 'requester', 'pembuat', 'nama pembuat'], true)) {
                $result['requested_by'] = $value;
            } elseif (in_array($key, ['date requested', 'date_requested', 'date', 'tanggal request', 'tanggal'], true)) {
                $result['date_requested_raw'] = $value;
            } elseif (in_array($key, ['description of work requested', 'description', 'deskripsi', 'deskripsi case'], true)) {
                $result['description'] = $value;
            } elseif (in_array($key, ['location', 'location office', 'lokasi', 'office'], true)) {
                $result['location'] = $value;
            }
        }

        return $result;
    }

    /**
     * Parse English or Indonesian date strings (including DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY, DD/MM/YY, YYYY-MM-DD, and text months) into a Carbon instance.
     */
    private function parseFlexibleDate(string $dateStr): ?Carbon
    {
        $cleaned = trim($dateStr);
        if ($cleaned === '') {
            return null;
        }

        // Strip leading day name if present (e.g. "Rabu, 18/11/2026" or "Wednesday, 18 Nov 2026")
        $cleaned = preg_replace(
            '/^(senin|selasa|rabu|kamis|jum\'?at|sabtu|minggu|monday|tuesday|wednesday|thursday|friday|saturday|sunday|mon|tue|wed|thu|fri|sat|sun)\s*,?\s*/iu',
            '',
            $cleaned
        ) ?? $cleaned;

        // 1. Check numeric date formats with /, -, or . (e.g., 18/11/2026, 18-11-2026, 18.11.2026, 18/11/26)
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})(?:\s+.*)?$/', $cleaned, $m)) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            $year = (int) $m[3];
            if ($year < 100) {
                $year += ($year >= 70 ? 1900 : 2000);
            }

            // Default to DD/MM/YYYY (Indonesian/International standard)
            if ($second >= 1 && $second <= 12 && checkdate($second, $first, $year)) {
                return Carbon::createSafe($year, $second, $first, 0, 0, 0);
            }

            // Fallback to MM/DD/YYYY if second > 12 (e.g., 11/18/2026)
            if ($first >= 1 && $first <= 12 && checkdate($first, $second, $year)) {
                return Carbon::createSafe($year, $first, $second, 0, 0, 0);
            }

            return null;
        }

        // 2. Check YYYY/MM/DD or YYYY-MM-DD or YYYY.MM.DD
        if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})(?:\s+.*)?$/', $cleaned, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];

            if (checkdate($month, $day, $year)) {
                return Carbon::createSafe($year, $month, $day, 0, 0, 0);
            }

            return null;
        }

        // 3. Translate Indonesian full and short month names to English
        $indoToEng = [
            'januari' => 'January',
            'jan' => 'Jan',
            'februari' => 'February',
            'pebruari' => 'February',
            'feb' => 'Feb',
            'peb' => 'Feb',
            'maret' => 'March',
            'mar' => 'Mar',
            'april' => 'April',
            'apr' => 'Apr',
            'mei' => 'May',
            'juni' => 'June',
            'jun' => 'Jun',
            'juli' => 'July',
            'jul' => 'Jul',
            'agustus' => 'August',
            'agu' => 'Aug',
            'agt' => 'Aug',
            'ags' => 'Aug',
            'september' => 'September',
            'sept' => 'Sep',
            'sep' => 'Sep',
            'oktober' => 'October',
            'okt' => 'Oct',
            'november' => 'November',
            'nopember' => 'November',
            'nov' => 'Nov',
            'nop' => 'Nov',
            'desember' => 'December',
            'des' => 'Dec',
        ];

        $normalized = preg_replace_callback('/\b([a-zA-Z]+)\b/', function ($matches) use ($indoToEng) {
            $lower = mb_strtolower($matches[1]);
            return $indoToEng[$lower] ?? $matches[1];
        }, $cleaned);

        try {
            return Carbon::parse($normalized)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
