<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mở rộng kích thước cột để chứa encrypted data (Laravel Crypt)
     * Encrypted data dài hơn ~3x so với plaintext
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // nfc_token_hash: từ VARCHAR(64) → TEXT
            $table->text('nfc_token_hash')->nullable()->change();

            // biometric_id: từ VARCHAR(255) → TEXT
            $table->text('biometric_id')->nullable()->change();
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
            $table->string('nfc_token_hash', 64)->nullable()->change();
            $table->string('biometric_id', 255)->nullable()->change();
        });
    }
};
