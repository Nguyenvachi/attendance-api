<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/seed-demo', function() {
    if (\App\Models\User::where('email', 'admin@demo.com')->exists()) {
        return response()->json(['message' => 'Data already seeded'], 200);
    }
    
    \App\Models\User::create(['name' => 'Admin', 'email' => 'admin@demo.com', 'password' => bcrypt('Admin@123'), 'role' => 'admin', 'status' => 'active', 'hourly_rate' => 25000]);
    \App\Models\User::create(['name' => 'Manager', 'email' => 'manager@demo.com', 'password' => bcrypt('Manager@123'), 'role' => 'manager', 'status' => 'active', 'hourly_rate' => 20000]);
    for($i=1; $i<=5; $i++) {
        \App\Models\User::create(['name' => "Staff $i", 'email' => "staff$i@demo.com", 'password' => bcrypt('Staff@123'), 'role' => 'staff', 'status' => 'active', 'nfc_card_id' => "NFC00$i", 'hourly_rate' => 20000]);
    }
    \App\Models\Shift::create(['name' => 'Morning', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_minutes' => 60, 'gps_enabled' => true, 'gps_latitude' => 10.762622, 'gps_longitude' => 106.660172, 'gps_radius_meters' => 500]);
    \App\Models\Shift::create(['name' => 'Evening', 'start_time' => '13:00:00', 'end_time' => '22:00:00', 'break_minutes' => 60]);
    
    return response()->json(['message' => 'Demo data seeded!', 'admin' => 'admin@demo.com / Admin@123', 'staff' => 'staff1-5@demo.com / Staff@123'], 201);
})->middleware('throttle:3,60');
