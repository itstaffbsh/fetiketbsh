<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_no',
        'department_id',
        'department_name',
        'raw_department',
        'requested_by',
        'date_requested',
        'completion_deadline',
        'location',
        'description',
        'status',
        'duration',
        'solution',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'date_requested' => 'date:Y-m-d',
            'completion_deadline' => 'date:Y-m-d',
            'completed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    /**
     * Normalize a work order number (e.g. "1520" -> "IT01520", "it01520" -> "IT01520").
     */
    public static function normalizeWorkOrderNo(string $input): string
    {
        $cleaned = strtoupper(trim(ltrim($input, '#')));
        if (preg_match('/^(\d+)$/', $cleaned, $m)) {
            return 'IT' . str_pad($m[1], 5, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^IT(\d+)$/i', $cleaned, $m)) {
            return 'IT' . str_pad($m[1], 5, '0', STR_PAD_LEFT);
        }
        return $cleaned;
    }

    /**
     * Generate the next work order number based on the last ticket's work_order_no + 1.
     */
    public static function generateNextWorkOrderNo(): string
    {
        $latest = self::orderByDesc('id')->first();
        if (! $latest) {
            return 'IT01501';
        }

        $prefix = 'IT';
        $padLen = 5;

        if (preg_match('/^([A-Za-z]*)(\d+)$/', trim($latest->work_order_no), $matches)) {
            if ($matches[1] !== '') {
                $prefix = strtoupper($matches[1]);
            }
            $padLen = max(5, strlen($matches[2]));
            $nextNumber = ((int) $matches[2]) + 1;
        } else {
            $nextNumber = 1500 + $latest->id + 1;
        }

        do {
            $candidate = $prefix . str_pad((string) $nextNumber, $padLen, '0', STR_PAD_LEFT);
            $exists = self::where('work_order_no', $candidate)->exists();
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidate;
    }

    /**
     * Automatically update any 'open' ticket whose date_requested is at least 1 day before today to 'wo_issued' ("WO has Been Issued").
     */
    public static function syncOverdueStatuses(): void
    {
        $today = Carbon::today()->toDateString();

        self::where('status', 'open')
            ->whereDate('date_requested', '<', $today)
            ->update(['status' => 'wo_issued']);
    }

    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'done' => 'Done',
            'wo_issued' => 'WO has Been Issued',
            default => 'Open',
        };
    }
}
