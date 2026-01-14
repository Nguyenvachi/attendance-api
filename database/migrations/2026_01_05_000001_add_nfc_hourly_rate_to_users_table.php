<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migration thêm cột nfc_uid (mã thẻ NFC) và hourly_rate (lương/giờ) vào bảng users
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nfc_uid')->unique()->nullable()->after('google_id')
                  ->comment('Mã thẻ NFC của nhân viên - dùng cho chấm công Kiosk');
            $table->decimal('hourly_rate', 10, 2)->default(0)->after('role')
                  ->comment('Lương theo giờ (VNĐ) - dùng để tính lương tự động');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nfc_uid', 'hourly_rate']);
        });
    }
};
