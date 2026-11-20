<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'sub_departments',
    ];

    protected function casts(): array
    {
        return [
            'sub_departments' => 'array',
        ];
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Match a raw department string case-insensitively against sub_departments (or department name).
     */
    public static function matchBySubDepartment(string $rawInput): ?self
    {
        $needle = mb_strtolower(trim($rawInput));
        if ($needle === '') {
            return null;
        }

        $departments = self::all();

        // 1. Check sub_departments first
        foreach ($departments as $dept) {
            $subs = is_array($dept->sub_departments) ? $dept->sub_departments : [];
            foreach ($subs as $sub) {
                if (mb_strtolower(trim((string) $sub)) === $needle) {
                    return $dept;
                }
            }
        }

        // 2. Also match if input matches the parent department name directly
        foreach ($departments as $dept) {
            if (mb_strtolower(trim($dept->name)) === $needle) {
                return $dept;
            }
        }

        return null;
    }
}
