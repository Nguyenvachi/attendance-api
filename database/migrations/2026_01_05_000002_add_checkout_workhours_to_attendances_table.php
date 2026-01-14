<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migration thêm cột check_out_time (giờ ra) và work_hours (tổng giờ làm) vào bảng attendances
     *
     * @return void
     */
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dateTime('check_out_time')->nullable()->after('check_in_time')
                  ->comment('Thời gian check-out (giờ ra) - tự động tính khi quẹt thẻ lần 2');
            $table->decimal('work_hours', 5, 2)->nullable()->after('check_out_time')
                  ->comment('Tổng số giờ làm việc = check_out_time - check_in_time');
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
            $table->dropColumn(['check_out_time', 'work_hours']);
        });
    }
};
