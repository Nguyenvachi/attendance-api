<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->double('latitude')->nullable()->after('code');
            $table->double('longitude')->nullable()->after('latitude');
            $table->integer('radius')->default(100)->after('longitude')->comment('Bán kính cho phép (mét)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'radius']);
        });
    }
};
