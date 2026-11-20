<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_summary_and_guest_work_orders_work_without_auth(): void
    {
        $summaryRes = $this->getJson('/api/public/summary');
        $summaryRes->assertOk()
            ->assertJsonStructure([
                'summary' => ['total', 'done', 'open', 'today'],
                'chart_30_days',
            ]);

        $this->assertEquals(28, $summaryRes->json('summary.total'));
        $this->assertCount(30, $summaryRes->json('chart_30_days'));

        $listRes = $this->getJson('/api/public/work-orders?search=IT01515');
        $listRes->assertOk();
        $this->assertCount(1, $listRes->json('data'));
        $this->assertEquals('IT01515', $listRes->json('data.0.work_order_no'));
    }

    public function test_login_issues_daily_expiring_sanctum_token(): void
    {
        $res = $this->postJson('/api/auth/login', [
            'email' => 'shahin@hexsupport.io',
            'password' => 'password',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['token', 'expires_at', 'user']);

        $user = User::where('email', 'shahin@hexsupport.io')->first();
        $tokenRecord = $user->tokens()->first();
        $this->assertNotNull($tokenRecord->expires_at);
    }

    public function test_quick_paste_auto_parses_maps_department_and_generates_copy_output(): void
    {
        $admin = User::where('email', 'shahin@hexsupport.io')->first();

        $rawPaste = implode("\n", [
            'Department: finance',
            'Requested By: alfin',
            'Date Requested: 19 November 2026',
            'Description of Work Requested: printer cant work properly',
            'Location: Head Office',
        ]);

        $res = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/work-orders', [
            'raw_text' => $rawPaste,
        ]);

        $res->assertCreated();
        $data = $res->json('data');

        // Auto-mapped finance -> Accounting
        $this->assertEquals('Accounting', $data['department_name']);
        $this->assertEquals('finance', $data['raw_department']);
        // Next number after seeded IT01518 is IT01519
        $this->assertEquals('IT01519', $data['work_order_no']);
        // 19 November 2026 + 2 calendar days = 21 November 2026
        $this->assertEquals('2026-11-21', $data['completion_deadline']);
        $this->assertEquals('21 November 2026', $data['completion_deadline_label']);

        $expectedCopy = implode("\n", [
            'Department: finance',
            'Requested By: alfin',
            'Date Requested: 19 November 2026',
            'Description of Work Requested: printer cant work properly',
            'Location: Head Office',
            '',
            'IT confirmed for the work order',
            'Work Order No: IT01519',
            'Completion Deadline: 21 November 2026',
        ]);

        $this->assertEquals($expectedCopy, $data['copy_text']);
    }

    public function test_quick_paste_unmatched_department_prompts_manual_selection(): void
    {
        $admin = User::where('email', 'shahin@hexsupport.io')->first();

        $rawPaste = implode("\n", [
            'Department: unknown_sub_dept',
            'Requested By: alfin',
            'Date Requested: 19 November 2026',
            'Description of Work Requested: printer cant work properly',
            'Location: Head Office',
        ]);

        $res = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/work-orders', [
            'raw_text' => $rawPaste,
        ]);

        $res->assertStatus(422)
            ->assertJson(['requires_manual_department' => true]);
    }

    public function test_soft_deleting_department_does_not_break_existing_work_orders(): void
    {
        $admin = User::where('email', 'shahin@hexsupport.io')->first();
        $accounting = Department::where('name', 'Accounting')->first();

        $delRes = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/departments/{$accounting->id}");
        $delRes->assertOk();

        $this->assertSoftDeleted('departments', ['id' => $accounting->id]);

        // Existing work orders linked to Accounting still load without error
        $woRes = $this->getJson('/api/public/work-orders?search=Accounting');
        $woRes->assertOk();
        $this->assertNotEmpty($woRes->json('data'));
    }

    public function test_logged_in_admin_cannot_delete_own_account(): void
    {
        $admin = User::where('email', 'shahin@hexsupport.io')->first();

        $res = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/admins/{$admin->id}");
        $res->assertStatus(422);
    }
}
