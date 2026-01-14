<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPRINT 2: Thêm timezone column để xử lý đúng giờ chấm công theo vùng
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
            $table->string('timezone', 50)->default('Asia/Ho_Chi_Minh')->after('break_hours')->comment('Timezone của thiết bị chấm công');
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
            $table->dropColumn('timezone');
        });
    }
};
