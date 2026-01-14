<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'code',
        'latitude',
        'longitude',
        'radius',
    ];

    /**
     * Relationship: Shift có nhiều attendances
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Tính khoảng cách giữa 2 tọa độ GPS (Haversine Formula)
     *
     * @param  float  $userLat Vĩ độ người dùng
     * @param  float  $userLng Kinh độ người dùng
     * @return float Khoảng cách tính bằng mét
     */
    public function calculateDistance($userLat, $userLng)
    {
        $earthRadius = 6371000; // Bán kính trái đất (mét)

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($userLat);
        $lonTo = deg2rad($userLng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // Khoảng cách mét
    }

    /**
     * BỔ SUNG: Tự động phát hiện ca làm việc dựa vào giờ check-in
     * Logic: Tìm ca có khoảng thời gian bao phủ giờ hiện tại
     * Fallback: Trả về shift_id = 1 nếu không tìm thấy ca phù hợp
     *
     * @param  \Carbon\Carbon|null  $time Thời gian cần check (default: now())
     * @return int Shift ID
     */
    public static function detectShiftByTime($time = null)
    {
        $time = $time ?? now();
        $currentTime = $time->format('H:i:s');

        // Tìm ca có start_time <= current_time <= end_time
        // Lưu ý: Nếu end_time < start_time (ca qua đêm), cần logic đặc biệt
        $shift = self::where(function ($query) use ($currentTime) {
            // Trường hợp thường (ca trong ngày): start <= current <= end
            $query->where('start_time', '<=', $currentTime)
                  ->where('end_time', '>=', $currentTime);
        })->orWhere(function ($query) use ($currentTime) {
            // Trường hợp ca qua đêm: end < start (VD: 20:00 - 02:00)
            $query->where('start_time', '>', 'end_time')
                  ->where(function ($q) use ($currentTime) {
                      // Hiện tại >= start (VD: 21:00 >= 20:00) HOẶC hiện tại <= end (VD: 01:00 <= 02:00)
                      $q->where('start_time', '<=', $currentTime)
                        ->orWhere('end_time', '>=', $currentTime);
                  });
        })->first();

        // Fallback: Nếu không tìm thấy ca phù hợp, trả về shift_id = 1 (ca mặc định)
        return $shift ? $shift->id : 1;
    }
}
