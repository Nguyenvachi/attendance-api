<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.


## BỔ SUNG: Đồng bộ báo cáo lên Google Sheets

Tính năng này **tách biệt hoàn toàn khỏi NFC** (không đụng route/luồng NFC), mục tiêu là **đẩy thống kê/báo cáo** lên Google Sheets để đồng bộ danh sách.

### 1) Chuẩn bị Google Sheet + Service Account
- Tạo Google Cloud Project và bật **Google Sheets API**.
- Tạo **Service Account** và tải file credentials JSON.
- Mở Google Sheet bạn muốn ghi dữ liệu → bấm Share → share cho email của service account (dạng `...@...iam.gserviceaccount.com`).

### 2) Cấu hình .env
Trong `server_api/.env` (hoặc `.env.testing` khi test), thêm:
- `GOOGLE_SHEETS_SPREADSHEET_ID=...` (ID nằm trong URL của sheet)
- Chọn 1 trong 2 cách cấu hình credentials:
	- `GOOGLE_SHEETS_CREDENTIALS_PATH=D:\path\to\service-account.json`
	- hoặc `GOOGLE_SHEETS_CREDENTIALS_JSON=` (JSON raw hoặc base64(JSON))
- Tuỳ chọn tên tab:
	- `GOOGLE_SHEETS_STATISTICS_SHEET=Statistics`
	- `GOOGLE_SHEETS_PAYROLL_SHEET=Payroll`

### 3) API export
Các endpoint dưới đây yêu cầu `auth:sanctum` và role `manager,admin`.

Mặc định hệ thống chạy theo chế độ **replace** (chuẩn vận hành):
- Khi sync lại cùng một kỳ (`period + start_date + end_date`), hệ thống sẽ **xoá các dòng cũ của kỳ đó** rồi ghi dữ liệu mới → tránh trùng.
- Nếu bạn muốn **lưu lịch sử** (append-only), truyền thêm `"mode": "append"`.

- Export thống kê attendance (late/điểm danh) lên sheet:
	- `POST /api/google-sheets/attendance-statistics`
	- Body ví dụ:
		- Weekly: `{ "period": "weekly", "year": 2026, "week": 2 }`
		- Monthly: `{ "period": "monthly", "year": 2026, "month": 1 }`
		- Quarterly: `{ "period": "quarterly", "year": 2026, "quarter": 1 }`
		- Yearly: `{ "period": "yearly", "year": 2026 }`
		- Append-only (lưu lịch sử): `{ "period": "monthly", "year": 2026, "month": 1, "mode": "append" }`

- Export payroll tổng hợp lên sheet:
	- `POST /api/google-sheets/payroll`
	- Body ví dụ:
		- `{ "period": "monthly", "year": 2026, "month": 1 }`
		- Append-only (lưu lịch sử): `{ "period": "monthly", "year": 2026, "month": 1, "mode": "append" }`

### 4) Dữ liệu ghi lên sheet
- Tab `Statistics`: mỗi dòng tương ứng **1 lần điểm danh** (theo ngày) với các cột như `user_name`, `check_in`, `shift_start`, `is_late`, `late_minutes`...
- Tab `Payroll`: mỗi dòng tương ứng **1 nhân viên** trong kỳ với `total_work_hours`, `total_salary`...

### 5) Auto-sync theo lịch (tuỳ chọn)
Mặc định module sync **thủ công**. Nếu muốn tự động sync theo lịch, bật Laravel Scheduler:

- Trong `.env`:
	- `GOOGLE_SHEETS_AUTO_SYNC_ENABLED=true`
	- `GOOGLE_SHEETS_AUTO_SYNC_DAILY_AT=23:55` (Statistics: export tháng hiện tại mỗi ngày)
	- `GOOGLE_SHEETS_AUTO_SYNC_PAYROLL_DAY=1`
	- `GOOGLE_SHEETS_AUTO_SYNC_PAYROLL_AT=00:10` (Payroll: export **tháng trước** để chốt lương)

- Trên server chạy scheduler:
	- Linux (cron): chạy `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
	- Windows: chạy nền `php artisan schedule:work` (Task Scheduler) hoặc set task gọi `schedule:run` mỗi phút.

## BỔ SUNG: Chấm công khuôn mặt (Face Recognition - Embedding)

Module này **tách riêng khỏi NFC** (không sửa luồng `/api/kiosk/attendance`). Backend chỉ lưu **embedding/vector** và cung cấp directory cho Kiosk; việc nhận diện (so khớp vector) thực hiện ở FE.

### 1) Schema
- Bảng: `user_face_embeddings`
- Unique: `(user_id, model_version)`

### 2) Env (tuỳ chọn)
- `FACE_MODEL_VERSION=mobilefacenet_v1` (default model version)
- `KIOSK_FACE_TOKEN=` (nếu set khác rỗng thì Kiosk phải gửi header `X-Kiosk-Token` khi gọi face-directory)

### 3) API
- Enroll (auth:sanctum)
	- Staff tự enroll: `POST /api/users/face/enroll`
	- Manager/Admin enroll cho user: `PUT /api/users/{id}/face/enroll`
	- Body:
		- `embedding` (string JSON array)
		- `embedding_dim` (int)
		- `model_version` (string)
		- `sample_count` (int)

- Kiosk (public)
	- Directory: `GET /api/kiosk/face-directory?model_version=...`
		- Header (tuỳ chọn): `X-Kiosk-Token: ...`
	- Attendance face: `POST /api/kiosk/attendance-face`
		- Body: `{ "user_id": 1, "match_score": 0.93, "model_version": "..." }`

### 4) Demo nhanh (emulator)
1) Đăng nhập Admin/Manager trên app.
2) Vào Quản lý nhân viên → menu (⋮) → **Đăng ký khuôn mặt** → chụp 3–5 mẫu → gửi.
3) Vào Kiosk → **Chấm công khuôn mặt (AI)** → đứng 1 người trước camera → chớp mắt (anti-spoof tối thiểu) → hệ thống tự check-in/out.

Ghi chú: FE hiện có chế độ `baseline_pixel_v1` (demo, không cần file model). Nếu muốn dùng model TFLite thật (vd `mobilefacenet_v1`), đặt file tại `FE/assets/models/face_embedding.tflite`.

## Security Features (SPRINT 1)

Hệ thống đã triển khai các biện pháp bảo mật quan trọng sau:

### 🔐 1. Audit Log (Nhật ký hoạt động)
- **Mục đích**: Theo dõi tất cả thao tác CRUD trên hệ thống để phục vụ audit và điều tra sự cố.
- **Cơ chế**: 
  - Middleware `AuditLogMiddleware` tự động log mọi request POST/PUT/PATCH/DELETE
  - Lưu trữ: user_id, action, model, model_id, old_data (trước khi sửa), new_data (sau khi sửa), ip_address, user_agent
  - Bảng: `audit_logs` với index tối ưu cho query theo (model, model_id) và created_at
- **API**: 
  - `GET /api/audit-logs` (Admin only) - Xem nhật ký với filter (user_id, model, action, date_from, date_to)
  - `GET /api/audit-logs/{id}` (Admin only) - Chi tiết 1 log entry
- **Performance**: Graceful error handling - log failure không block request chính

### 🔒 2. Encryption at Rest (Mã hóa dữ liệu nhạy cảm)
- **Mục đích**: Bảo vệ dữ liệu nhạy cảm khỏi truy cập trái phép khi database bị leak
- **Dữ liệu được mã hóa**:
  - `users.nfc_token_hash` - Token xác thực NFC payload
  - `users.biometric_id` - ID sinh trắc học (vân tay/khuôn mặt)
- **Cơ chế**: 
  - Laravel Eloquent Encrypted Cast - tự động encrypt/decrypt trong model
  - Migration `2026_01_13_000002_encrypt_sensitive_data.php` - encrypt dữ liệu cũ (idempotent, có rollback)
  - Encryption key: APP_KEY trong .env (generate: `php artisan key:generate`)
- **⚠️ QUAN TRỌNG**: 
  - Backup database trước khi chạy migration encrypt
  - KHÔNG đổi APP_KEY sau khi encrypt (data sẽ không decrypt được)
  - Lưu APP_KEY ở nơi an toàn (vault/secrets manager)

### ⏱️ 3. Rate Limiting (Giới hạn request)
- **Mục đích**: Chống brute-force attack, DDoS và abuse
- **Giới hạn**:
  - Login/Auth: **5 requests/phút** (`throttle:5,1`)
  - Kiosk attendance: **10 requests/phút** (`throttle:10,1`)
  - API endpoints khác: **60 requests/phút** (default `throttle:api`)
- **Response**: HTTP 429 với JSON `{"status": "error", "message": "...", "retry_after": X}`
- **Frontend**: Auto-detect 429 error trong `api_service.dart` và hiển thị thời gian retry

### 🔑 4. Password Policy (Chính sách mật khẩu mạnh)
- **Yêu cầu**:
  - Tối thiểu 8 ký tự
  - Có chữ hoa (A-Z)
  - Có chữ thường (a-z)
  - Có số (0-9)
  - Có ký tự đặc biệt (!@#$%^&*...)
  - Không nằm trong danh sách password bị leak (HaveIBeenPwned API)
- **Áp dụng**: AuthController::setPassword(), UserController::store/update()
- **Frontend**: Password strength indicator (Yếu/Trung bình/Mạnh) với visual feedback

### 📁 5. File Upload Security (Bảo mật upload file)
- **Validation**:
  - MIME type whitelist: **jpg, jpeg, png** only (không cho upload file khác)
  - File size limit: **5MB max** (5120 KB)
  - Real image verification: `getimagesize()` - đảm bảo file thực sự là ảnh hợp lệ
- **Storage**:
  - Random filename: `Str::random(40) + extension` - tránh path traversal
  - Private disk: Chỉ accessible qua signed URL hoặc authentication check
- **Áp dụng**: AttendanceProofController (upload ảnh xác thực chấm công)

### 📋 Setup Instructions

1. **Chạy migrations**:
```bash
cd server_api
php artisan migrate
```

2. **Verify audit logs**:
```bash
php artisan tinker
>>> DB::table('audit_logs')->count()  # Kiểm tra bảng đã tạo
```

3. **Test encryption**:
```bash
php artisan tinker
>>> $user = User::first()
>>> $user->nfc_token_hash  # Laravel tự động decrypt khi access
```

4. **Test rate limiting**:
```bash
# Gửi 10 requests liên tục đến /api/login → request thứ 6 sẽ nhận 429
for i in {1..10}; do curl -X POST http://localhost:8000/api/login -d '{"email":"test@test.com","password":"wrong"}'; done
```

5. **Environment variables (.env)**:
```env
APP_KEY=base64:...  # QUAN TRỌNG: Không đổi sau khi encrypt data!
```

### 🧪 Testing
```bash
# Run test suite
vendor/bin/phpunit

# Specific security tests
vendor/bin/phpunit tests/Feature/AuditLogTest.php
vendor/bin/phpunit tests/Feature/RateLimitTest.php
```

### 📚 Dependencies
- `doctrine/dbal` - Cho schema changes (extend column types)
- Laravel Crypt - Built-in encryption
- Laravel Throttle - Built-in rate limiting

---

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

---

## SPRINT 2: Logic Critical Fixes & Enhancements (Jan 2026)

### **Tổng quan**
SPRINT 2 tập trung vào **sửa các thiếu sót business logic critical** để app production-ready:
1. **Shift Overlap Validation** - Xử lý ca qua đêm (22:00-02:00)
2. **Timezone Handling** - Đồng bộ timezone client/server (GMT+7)
3. **Race Condition Prevention** - DB transaction lock cho concurrent requests
4. **Overtime Calculation** - Tính lương chính xác (regular/overtime/double)
5. **Department Hierarchy** - Manager chỉ thấy team mình

### **Backend Changes**

#### 1. Shift Overlap Validation
**File:** `app/Http/Controllers/API/ShiftController.php`
- **Cải tiến:** `validateShiftOverlap()` method xử lý 4 cases:
  - Same-day shifts (08:00-17:00)
  - Overnight shifts (22:00-06:00)
  - Mixed (same-day vs overnight)
  - Overnight vs overnight (split thành evening + morning ranges)
- **Test:** `tests/Feature/ShiftOverlapTest.php` (8 test cases)

#### 2. Timezone Support
**Files:** 
- `config/app.php`: Timezone = `Asia/Ho_Chi_Minh`
- `app/Models/Attendance.php`: Cast `check_in_time`, `check_out_time` sang `datetime:Asia/Ho_Chi_Minh`
- **Migration:** `2026_01_13_171256_add_timezone_to_attendances_table.php`
  - Thêm column `timezone` VARCHAR(50) default 'Asia/Ho_Chi_Minh'

#### 3. Race Condition Prevention
**Files:** `app/Http/Controllers/API/KioskController.php`, `AttendanceController.php`
- **Wrap logic chấm công trong:**
  ```php
  DB::transaction(function() {
      $attendance = Attendance::where(...)->lockForUpdate()->first();
      // Check-in/check-out logic
  });
  ```
- **Migration:** `2026_01_13_171320_add_unique_daily_attendance_constraint.php`
  - Composite index `idx_user_checkin` ['user_id', 'check_in_time']
- **Test:** `tests/Feature/RaceConditionTest.php` (4 test cases) ✅ **All passed**

#### 4. Overtime Calculation
**Files:**
- **Migration:** `2026_01_13_171226_add_overtime_columns_to_attendances_table.php`
  - Thêm `regular_hours`, `overtime_hours`, `overtime_double_hours`, `break_hours` (decimal 8,2)
- `config/payroll.php`: Tạo mới với rates:
  - `overtime_rate`: 1.5 (tăng 50%)
  - `overtime_double_rate`: 2.0 (tăng 100%)
  - `weekend_multiplier`: 2.0 (gấp đôi Sat/Sun)
  - Break time: 12:00-13:00 (trừ 1h nghỉ trưa)
- `app/Models/Attendance.php`: Method `calculateSalary()` với logic:
  - Regular: 0-8h x hourly_rate
  - Overtime: 8-10h x hourly_rate x 1.5
  - Double: >10h x hourly_rate x 2.0
  - Minus break: 12:00-13:00
  - Weekend bonus: x2.0 nếu Sat/Sun
- **Auto-call:** `KioskController`, `AttendanceController` tự động gọi `calculateSalary()` sau checkout
- **API Response:** Thêm `regular_hours`, `overtime_hours`, `overtime_double_hours`, `break_hours`, `earned_salary`

#### 5. Department Hierarchy
**Files:**
- **Migration:** `2026_01_13_171345_create_departments_table.php`
  - Columns: `id`, `name`, `parent_id` (nested), `manager_id` (FK users), `description`
- **Migration:** `2026_01_13_171349_add_department_id_to_users_table.php`
  - Thêm `department_id` FK departments vào users
- `app/Models/Department.php`: 
  - Relationships: `parent()`, `children()`, `manager()`, `users()`
  - Methods: `getAllDepartmentIds()` (recursive), `canBeAccessedBy(User $user)`
- `app/Models/User.php`: 
  - Relationship: `department()`
  - Scope: `scopeAccessibleBy($query, User $authUser)` - filter by department
- `app/Http/Controllers/API/DepartmentController.php`: Full CRUD với validation
- **Routes:** `/api/departments` với middleware `role:admin` (POST/PUT/DELETE), `role:manager,admin` (GET)
- **Scope applied:** `PayrollController`, `ReportController` dùng `User::accessibleBy($authUser)` để filter users

### **Frontend Changes (Flutter)**

#### 1. Shift Overlap Error Handling
**File:** `lib/screens/manager/shift_management_screen.dart`
- Catch error 422 từ API → Show dialog thay vì SnackBar
- Dialog hiển thị chi tiết message "Ca trùng lấn với ca..."

#### 2. Timezone Sent to API
**File:** `lib/services/api_service.dart`
- Methods `kioskCheckIn()`, `attendance()` thêm parameter `timezone`
- Send `DateTime.now().timeZoneName` (fallback: 'Asia/Ho_Chi_Minh')

#### 3. Kiosk Button Disable on Submit
**File:** `lib/screens/kiosk_screen.dart`
- Disable "Gửi chấm công" button khi `_isProcessing = true`
- Show `CircularProgressIndicator` trong button khi processing

#### 4. Overtime Breakdown Display
**File:** `lib/screens/payroll_screen.dart`
- Detail card hiển thị breakdown:
  - Giờ cơ bản: `regular_hours` x hourly_rate
  - Tăng ca (x1.5): `overtime_hours` x (hourly_rate * 1.5)
  - Tăng ca (x2.0): `overtime_double_hours` x (hourly_rate * 2.0)
  - Giờ nghỉ trưa: `-break_hours`
- Helper method `_detailRow()` thêm parameter `isSubItem` để style sub-items

#### 5. Department Dropdown
**File:** `lib/screens/admin/user_management_screen.dart`
- Add `getDepartments()` method trong `api_service.dart`
- Load departments list trong `initState()`
- Dropdown "Phòng ban" trong Create/Edit User dialog (optional field)

### **Database Migrations Summary**
5 migrations đã chạy thành công (288ms total):
1. `2026_01_13_171226` - Overtime columns
2. `2026_01_13_171256` - Timezone column
3. `2026_01_13_171320` - Unique constraint
4. `2026_01_13_171345` - Departments table
5. `2026_01_13_171349` - Department_id to users

### **Testing Coverage**
- ✅ `ShiftOverlapTest.php`: 8 tests - Shift overlap validation
- ✅ `RaceConditionTest.php`: 4 tests - Concurrent request handling
- ✅ Code formatted: `dart format lib/` (38 files)

### **Breaking Changes**
- **None** - All changes backward compatible
- Old attendance records without overtime columns → calculateSalary() returns 0
- Users without department_id → accessibleBy() vẫn hoạt động (scope by role)

### **Next Steps (Optional)**
- Task 8: Display timezone badge in Attendance History (file không tồn tại → skip)
- Task 24: Dashboard filter by department (UI phức tạp 985 lines → skip)
- Monitoring: Track overtime calculation accuracy in production
- Performance: Add index on `attendances.department_id` nếu query chậm

---

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
