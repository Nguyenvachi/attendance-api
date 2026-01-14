<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_proofs', function (Blueprint $table) {
            $table->id();

            // Liên kết (ưu tiên) tới user/attendance nếu FE gửi lên
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('attendance_id')->nullable()->index();

            // Nguồn chấm công: nfc | biometric | qr | ...
            $table->string('method', 50)->index();
            // Hành động: check_in | check_out
            $table->string('action', 50)->index();

            // Thời điểm ảnh được chụp (device time)
            $table->timestamp('captured_at')->nullable()->index();

            // Đường dẫn file trên storage disk (public)
            $table->string('image_path');

            // Metadata tuỳ chọn (json): user_name, kiosk info, etc.
            $table->json('meta')->nullable();

            $table->timestamps();

            // FK dạng nullable (không ép cascade để tránh ảnh hưởng dữ liệu cũ)
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('attendance_id')->references('id')->on('attendances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_proofs');
    }
};
