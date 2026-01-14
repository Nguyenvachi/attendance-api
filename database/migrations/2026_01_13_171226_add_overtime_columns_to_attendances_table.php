<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPRINT 2: Thêm các cột overtime để tính lương chính xác hơn
 * - regular_hours: Giờ chuẩn (0-8h)
 * - overtime_hours: Tăng ca (8-10h) x1.5
 * - overtime_double_hours: Tăng ca gấp đôi (>10h) x2.0
 * - break_hours: Giờ nghỉ (không tính lương)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('regular_hours', 8, 2)->default(0)->after('work_hours')->comment('Giờ làm chuẩn (0-8h)');
            $table->decimal('overtime_hours', 8, 2)->default(0)->after('regular_hours')->comment('Giờ tăng ca (8-10h, x1.5)');
            $table->decimal('overtime_double_hours', 8, 2)->default(0)->after('overtime_hours')->comment('Giờ tăng ca gấp đôi (>10h, x2.0)');
            $table->decimal('break_hours', 8, 2)->default(0)->after('overtime_double_hours')->comment('Giờ nghỉ (không tính lương)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['regular_hours', 'overtime_hours', 'overtime_double_hours', 'break_hours']);
        });
    }
};
