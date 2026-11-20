<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('work_order_no')->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('department_name');
            $table->string('raw_department')->nullable();
            $table->string('requested_by');
            $table->date('date_requested');
            $table->date('completion_deadline');
            $table->string('location');
            $table->text('description');
            $table->string('status')->default('open');
            $table->string('duration')->nullable();
            $table->text('solution')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
