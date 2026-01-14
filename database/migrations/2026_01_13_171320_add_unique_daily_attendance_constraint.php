<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPRINT 2: Thêm unique constraint để prevent race condition
 * Ngăn duplicate check-in: 1 user chỉ check-in 1 lần/ngày cho 1 ca
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
        // Note: Không dùng unique constraint vì cần cho phép nhiều ca/ngày
        // Thay vào đó dùng DB::transaction() + lockForUpdate() trong code
        // Migration này giữ lại để document quyết định thiết kế

        // Có thể thêm composite index để tăng performance query
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['user_id', 'check_in_time'], 'idx_user_checkin');
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
            $table->dropIndex('idx_user_checkin');
        });
    }
};
