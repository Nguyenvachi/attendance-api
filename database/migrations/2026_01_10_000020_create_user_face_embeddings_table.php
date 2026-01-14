<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * BỔ SUNG: Lưu dữ liệu khuôn mặt dạng embedding/vector (không lưu ảnh để so sánh).
     * Mục tiêu: hỗ trợ kiosk nhận diện 100+ nhân viên bằng phép so khớp vector.
     */
    public function up(): void
    {
        Schema::create('user_face_embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();

            // Lưu vector dạng JSON string hoặc TEXT (vd: [0.12, -0.03, ...]).
            $table->longText('embedding')->comment('Face embedding vector (JSON array)');
            $table->unsignedSmallInteger('embedding_dim')->default(128);
            $table->string('model_version', 64)->default('mobilefacenet_v1');

            // Số mẫu đã gộp/đăng ký (để biết chất lượng enroll).
            $table->unsignedSmallInteger('sample_count')->default(1);
            $table->dateTime('registered_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'model_version']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_face_embeddings');
    }
};
