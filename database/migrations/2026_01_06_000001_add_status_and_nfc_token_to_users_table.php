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
     * - Khóa/Vô hiệu hóa tài khoản: is_active, deactivated_at, deactivated_by, deactivated_reason
     * - NFC "nội dung thẻ" (NDEF payload): nfc_token_hash, nfc_token_issued_at, nfc_token_version
     *
     * Lưu ý: Không dùng migrate:fresh theo yêu cầu dự án.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Trạng thái tài khoản
            $table->boolean('is_active')->default(true)->after('role')
                  ->comment('Trạng thái tài khoản: true=hoạt động, false=bị khóa');
            $table->dateTime('deactivated_at')->nullable()->after('is_active')
                  ->comment('Thời điểm tài khoản bị khóa');
            $table->unsignedBigInteger('deactivated_by')->nullable()->after('deactivated_at')
                  ->comment('User ID người khóa (admin/manager)');
            $table->string('deactivated_reason', 255)->nullable()->after('deactivated_by')
                  ->comment('Lý do khóa tài khoản');

            // NFC nội dung thẻ (token hash để verify payload)
            $table->string('nfc_token_hash', 64)->nullable()->unique()->after('nfc_uid')
                  ->comment('SHA-256 token dùng trong NFC payload (NDEF)');
            $table->dateTime('nfc_token_issued_at')->nullable()->after('nfc_token_hash')
                  ->comment('Thời điểm phát hành token NFC');
            $table->unsignedSmallInteger('nfc_token_version')->default(1)->after('nfc_token_issued_at')
                  ->comment('Version định dạng payload NFC');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'deactivated_at',
                'deactivated_by',
                'deactivated_reason',
                'nfc_token_hash',
                'nfc_token_issued_at',
                'nfc_token_version',
            ]);
        });
    }
};
