<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPRINT 2: Tạo bảng departments để phân cấp tổ chức
 * Hỗ trợ nested departments (phòng ban con)
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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Tên phòng ban (vd: Marketing, IT, HR)');
            $table->foreignId('parent_id')->nullable()->constrained('departments')->onDelete('cascade')->comment('Phòng ban cha (nested)');
            $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null')->comment('Quản lý phòng ban');
            $table->text('description')->nullable()->comment('Mô tả phòng ban');
            $table->timestamps();

            $table->index('parent_id');
            $table->index('manager_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('departments');
    }
};
