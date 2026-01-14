<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * BỔ SUNG: Audit log để track mọi thay đổi dữ liệu (compliance, security)
     *
     * @return void
     */
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action', 50); // create, update, delete, login, logout
            $table->string('model', 100)->nullable(); // User, Shift, Attendance, etc.
            $table->unsignedBigInteger('model_id')->nullable(); // ID của record bị thay đổi
            $table->json('old_data')->nullable(); // Dữ liệu trước khi sửa
            $table->json('new_data')->nullable(); // Dữ liệu sau khi sửa
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Index để tăng tốc query
            $table->index(['model', 'model_id']);
            $table->index('created_at');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};
