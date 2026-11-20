<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicTicketController extends Controller
{
    public function summary(): JsonResponse
    {
        WorkOrder::syncOverdueStatuses();

        $today = Carbon::today();

        $total = WorkOrder::count();
        $done = WorkOrder::where('status', 'done')->count();
        $open = WorkOrder::whereIn('status', ['open', 'wo_issued'])->count();
        $woIssued = WorkOrder::where('status', 'wo_issued')->count();
        $todayCount = WorkOrder::whereDate('created_at', $today)->count();

        // Build 30-day chart series (last 30 calendar days including today)
        $startDate = $today->copy()->subDays(29);

        $ticketsLast30Days = WorkOrder::whereDate('date_requested', '>=', $startDate->toDateString())
            ->whereDate('date_requested', '<=', $today->toDateString())
            ->get(['id', 'date_requested', 'status', 'created_at']);

        $grouped = [];
        foreach ($ticketsLast30Days as $ticket) {
            $dateKey = Carbon::parse($ticket->date_requested)->toDateString();
            if (! isset($grouped[$dateKey])) {
                $grouped[$dateKey] = ['total' => 0, 'done' => 0, 'open' => 0];
            }
            $grouped[$dateKey]['total']++;
            if ($ticket->status === 'done') {
                $grouped[$dateKey]['done']++;
            } else {
                $grouped[$dateKey]['open']++;
            }
        }

        $chart30Days = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $key = $day->toDateString();
            $counts = $grouped[$key] ?? ['total' => 0, 'done' => 0, 'open' => 0];

            $chart30Days[] = [
                'date' => $key,
                'label' => $day->format('d M'),
                'full_label' => $day->format('d F Y'),
                'total' => $counts['total'],
                'done' => $counts['done'],
                'open' => $counts['open'],
            ];
        }

        return response()->json([
            'summary' => [
                'total' => $total,
                'done' => $done,
                'open' => $open,
                'wo_issued' => $woIssued,
                'today' => $todayCount,
            ],
            'chart_30_days' => $chart30Days,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        WorkOrder::syncOverdueStatuses();

        $perPage = (int) $request->input('per_page', 10);
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

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn (WorkOrder $wo) => self::formatWorkOrder($wo));

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

    public function show(int $id): JsonResponse
    {
        WorkOrder::syncOverdueStatuses();

        $wo = WorkOrder::with('department')->findOrFail($id);

        return response()->json([
            'data' => self::formatWorkOrder($wo),
        ]);
    }

    public static function formatWorkOrder(WorkOrder $wo): array
    {
        $dateRequested = $wo->date_requested ? Carbon::parse($wo->date_requested) : null;
        $deadline = $wo->completion_deadline ? Carbon::parse($wo->completion_deadline) : null;

        $rawDept = $wo->raw_department ?: $wo->department_name;
        $dateRequestedFormatted = $dateRequested ? $dateRequested->format('d F Y') : '-';
        $deadlineFormatted = $deadline ? $deadline->format('d F Y') : '-';

        $copyText = implode("\n", [
            "Department: {$rawDept}",
            "Requested By: {$wo->requested_by}",
            "Date Requested: {$dateRequestedFormatted}",
            "Description of Work Requested: {$wo->description}",
            "Location: {$wo->location}",
            "",
            "IT confirmed for the work order",
            "Work Order No: {$wo->work_order_no}",
            "Completion Deadline: {$deadlineFormatted}",
        ]);

        return [
            'id' => $wo->id,
            'work_order_no' => $wo->work_order_no,
            'department_id' => $wo->department_id,
            'department' => $wo->department_name,
            'department_name' => $wo->department_name,
            'raw_department' => $wo->raw_department ?? $wo->department_name,
            'requested_by' => $wo->requested_by,
            'date_requested' => $dateRequested ? $dateRequested->format('Y-m-d') : null,
            'date_requested_label' => $dateRequestedFormatted,
            'completion_deadline' => $deadline ? $deadline->format('Y-m-d') : null,
            'completion_deadline_label' => $deadlineFormatted,
            'location' => $wo->location,
            'description' => $wo->description,
            'status' => $wo->status,
            'status_label' => WorkOrder::getStatusLabel($wo->status),
            'duration' => $wo->duration,
            'solution' => $wo->solution,
            'completed_at' => $wo->completed_at?->toIso8601String(),
            'completed_date' => $wo->completed_at ? Carbon::parse($wo->completed_at)->format('Y-m-d') : null,
            'completed_date_label' => $wo->completed_at ? Carbon::parse($wo->completed_at)->format('d F Y') : null,
            'created_at' => $wo->created_at?->toIso8601String(),
            'copy_text' => $copyText,
        ];
    }
}
