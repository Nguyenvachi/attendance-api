<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_sheets_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // attendance-statistics | payroll
            $table->string('type', 50);

            $table->string('period', 20)->nullable();
            $table->integer('year')->nullable();
            $table->integer('week')->nullable();
            $table->integer('month')->nullable();
            $table->integer('quarter')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->string('mode', 20)->nullable();
            $table->string('sheet', 100)->nullable();
            $table->integer('deleted_rows')->nullable();
            $table->integer('written_rows')->nullable();

            $table->string('status', 30)->nullable();
            $table->string('message')->nullable();

            $table->json('request_params')->nullable();
            $table->json('google_response')->nullable();

            $table->timestamps();

            $table->index(['type', 'period', 'year']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheets_exports');
    }
};
