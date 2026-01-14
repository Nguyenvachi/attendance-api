<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * BỔ SUNG (không thay thế code cũ):
     * Thêm trường lưu ID sinh trắc học (vân tay/khuôn mặt) cho user.
     * biometric_id: hash/ID do OS sinh ra khi đăng ký vân tay/Face ID (do FE gửi lên).
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('biometric_id', 255)->nullable()->unique()->after('nfc_token_version')
                  ->comment('ID sinh trắc học (vân tay/khuôn mặt) do FE/OS đăng ký');
            $table->dateTime('biometric_registered_at')->nullable()->after('biometric_id')
                  ->comment('Thời điểm đăng ký sinh trắc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['biometric_id', 'biometric_registered_at']);
        });
    }
};
