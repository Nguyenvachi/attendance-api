<?php

use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\AttendanceProofController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\FaceDirectoryController;
use App\Http\Controllers\API\FaceEnrollmentController;
use App\Http\Controllers\API\GoogleSheetsExportController;
use App\Http\Controllers\API\KioskController;
use App\Http\Controllers\API\KioskFaceController;
use App\Http\Controllers\API\KioskQrController;
use App\Http\Controllers\API\LeaveRequestController;
use App\Http\Controllers\API\PayrollController;
use App\Http\Controllers\API\PdfExportController;
use App\Http\Controllers\API\QrAttendanceController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\ShiftController;
use App\Http\Controllers\API\TwoFactorController;
use App\Http\Controllers\API\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// BỔ SUNG: Login routes với rate limiting (SPRINT 1 Security)
// 5 requests/min để chống brute force
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/auth/google', [AuthController::class, 'googleLogin']);
});

// ========== KIOSK ROUTES - KHÔNG CẦN AUTHENTICATION ==========
// Routes cho máy chấm công Kiosk (NFC/Biometric)
// BỔ SUNG: Rate limiting 10 req/min (SPRINT 1 Security)
Route::prefix('kiosk')->middleware('throttle:10,1')->group(function () {
    // Chấm công tự động (Check-in/Check-out)
    Route::post('/attendance', [KioskController::class, 'attendance']);

    // ========== KIOSK QR (SPRINT 3) ==========
    // Kiosk xin QR session động để nhân viên quét bằng app cá nhân.
    // Không ảnh hưởng NFC hiện tại.
    Route::post('/qr/session', [KioskQrController::class, 'createSession']);
    Route::get('/qr/session', [KioskQrController::class, 'getSession']);

    // BỔ SUNG: Upload ảnh xác thực chấm công (không thay đổi luồng NFC hiện tại)
    Route::post('/attendance-proof', [AttendanceProofController::class, 'kioskUpload']);

    // ========== FACE RECOGNITION (KIOSK) - TÁCH RIÊNG, KHÔNG ẢNH HƯỞNG NFC ==========
    // Kiosk tải danh sách embedding để cache/match
    Route::get('/face-directory', [FaceDirectoryController::class, 'index']);
    // Kiosk báo user đã match để thực hiện check-in/out
    Route::post('/attendance-face', [KioskFaceController::class, 'attendanceFace']);

    // Kiểm tra trạng thái hệ thống
    Route::get('/status', [KioskController::class, 'status']);
});

// Routes yêu cầu authentication
// BỔ SUNG: Apply audit log middleware cho tất cả authenticated routes (SPRINT 1 Security)
Route::middleware(['auth:sanctum', 'audit'])->group(function () {
    // Set password cho user Google - Yêu cầu đăng nhập
    Route::post('/auth/set-password', [AuthController::class, 'setPassword']);

    // Admin routes - Quản lý users
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        // BỔ SUNG: Khóa / mở khóa tài khoản nhân viên
        Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);

        // BỔ SUNG: Phát hành nội dung thẻ NFC (payload) để ghi NDEF
        Route::post('/users/{id}/nfc/issue', [UserController::class, 'issueNfcPayload']);

        // BỔ SUNG: Admin cập nhật biometric_id cho user
        Route::put('/users/{id}/biometric', [UserController::class, 'updateBiometric']);

        // Cập nhật mã thẻ NFC cho nhân viên
        Route::put('/users/{id}/nfc', [KioskController::class, 'updateNFC']);

        // BỔ SUNG: Admin xem danh sách ảnh xác thực chấm công
        Route::get('/attendance-proofs', [AttendanceProofController::class, 'index']);
        // BỔ SUNG: Realtime stream (SSE) cho ảnh xác thực
        Route::get('/attendance-proofs/stream', [AttendanceProofController::class, 'stream']);
        Route::get('/attendance-proofs/{id}', [AttendanceProofController::class, 'show']);

        // BỔ SUNG: Audit logs (SPRINT 1 Security)
        Route::get('/audit-logs', [App\Http\Controllers\API\AuditLogController::class, 'index']);
        Route::get('/audit-logs/{id}', [App\Http\Controllers\API\AuditLogController::class, 'show']);

        // BỔ SUNG: Department CRUD (SPRINT 2 Logic Critical - Admin only)
        Route::apiResource('departments', App\Http\Controllers\API\DepartmentController::class)
            ->only(['store', 'update', 'destroy']);
    });

    // Manager routes - Quản lý shifts (Admin cũng có quyền)
    Route::middleware('role:manager,admin')->group(function () {
        Route::get('/shifts', [ShiftController::class, 'index']);
        Route::post('/shifts', [ShiftController::class, 'store']);
        // BỔ SUNG: Update và Delete shift
        Route::put('/shifts/{id}', [ShiftController::class, 'update']);
        Route::delete('/shifts/{id}', [ShiftController::class, 'destroy']);

        // Chấm công thủ công (Manual attendance)
        Route::post('/admin/manual-attendance', [KioskController::class, 'manualAttendance']);

        // ========== GOOGLE SHEETS SYNC (Manager/Admin) ==========
        // BỔ SUNG: Đồng bộ thống kê/báo cáo lên Google Sheets (tách biệt NFC)
        Route::prefix('google-sheets')->group(function () {
            Route::post('/attendance-statistics', [GoogleSheetsExportController::class, 'exportAttendanceStatistics']);
            Route::post('/payroll', [GoogleSheetsExportController::class, 'exportPayroll']);
        });

        // ========== FACE ENROLLMENT (MANAGER/ADMIN) ==========
        Route::put('/users/{id}/face/enroll', [FaceEnrollmentController::class, 'enrollForUser']);

        // BỔ SUNG: Department GET (SPRINT 2 - Manager có thể xem department của team)
        Route::get('/departments', [App\Http\Controllers\API\DepartmentController::class, 'index']);
        Route::get('/departments/{id}', [App\Http\Controllers\API\DepartmentController::class, 'show']);
    });

    // Staff routes - Điểm danh (Tất cả roles đều có quyền)
    Route::post('/attendance', [AttendanceController::class, 'store']);
    // BỔ SUNG: Staff check-out
    Route::post('/attendance/checkout', [AttendanceController::class, 'checkout']);

    // ========== STAFF QR (KIOSK SESSION) ==========
    // Staff quét QR động trên kiosk để check-in/out.
    Route::post('/attendance/qr', [QrAttendanceController::class, 'store']);

    // BỔ SUNG: User tự đăng ký sinh trắc học (vân tay/khuôn mặt)
    Route::post('/users/biometric/register', [UserController::class, 'registerBiometric']);

    // ========== FACE ENROLLMENT (AUTH) ==========
    // Staff tự đăng ký khuôn mặt (embedding) cho chính mình
    Route::post('/users/face/enroll', [FaceEnrollmentController::class, 'enrollSelf']);

    // Leave Request routes - Quản lý đơn từ
    Route::get('/leaves', [LeaveRequestController::class, 'index']);
    Route::post('/leaves', [LeaveRequestController::class, 'store']);
    Route::put('/leaves/{id}/status', [LeaveRequestController::class, 'updateStatus'])
        ->middleware('role:manager,admin');

    // Report routes - Thống kê
    Route::get('/reports/monthly', [ReportController::class, 'monthly']);
    // BỔ SUNG: Báo cáo theo tuần/quý/năm
    Route::get('/reports/weekly', [ReportController::class, 'weekly']);
    Route::get('/reports/quarterly', [ReportController::class, 'quarterly']);
    Route::get('/reports/yearly', [ReportController::class, 'yearly']);

    // ========== PDF EXPORT - REPORTS ==========
    // BỔ SUNG: Xuất báo cáo thống kê (chấm công/đi trễ) dạng PDF
    Route::get('/reports/weekly/pdf', [PdfExportController::class, 'attendanceWeekly']);
    Route::get('/reports/monthly/pdf', [PdfExportController::class, 'attendanceMonthly']);
    Route::get('/reports/quarterly/pdf', [PdfExportController::class, 'attendanceQuarterly']);
    Route::get('/reports/yearly/pdf', [PdfExportController::class, 'attendanceYearly']);
    // BỔ SUNG: Gửi email báo cáo thống kê (manager/admin)
    Route::post('/reports/send-statistics-email', [ReportController::class, 'sendStatisticsEmail'])
        ->middleware('role:manager,admin');
    Route::get('/reports/today', [ReportController::class, 'today'])
        ->middleware('role:manager,admin');

    // ========== PAYROLL ROUTES - BÁO CÁO LƯƠNG ==========
    // Routes cho quản lý lương và gửi email (Admin/Manager only)
    Route::middleware('role:manager,admin')->prefix('payroll')->group(function () {
        // BỔ SUNG: Báo cáo lương tuần (ISO week)
        Route::get('/weekly', [PayrollController::class, 'weekly']);
        // Báo cáo lương tháng (tất cả nhân viên)
        Route::get('/report', [PayrollController::class, 'report']);

        // ========== PDF EXPORT - PAYROLL ==========
        // BỔ SUNG: Xuất báo cáo lương dạng PDF
        Route::get('/weekly/pdf', [PdfExportController::class, 'payrollWeekly']);
        Route::get('/report/pdf', [PdfExportController::class, 'payrollMonthly']);
        Route::get('/quarterly/pdf', [PdfExportController::class, 'payrollQuarterly']);
        Route::get('/yearly/pdf', [PdfExportController::class, 'payrollYearly']);

        // Gửi email báo cáo lương
        Route::post('/send-email', [PayrollController::class, 'sendEmail']);
        // BỔ SUNG: Gửi email báo cáo theo kỳ (weekly/quarterly/yearly)
        Route::post('/send-period-email', [PayrollController::class, 'sendPeriodEmail']);
        // Xem báo cáo lương của 1 nhân viên cụ thể
        Route::get('/employee/{id}', [PayrollController::class, 'employeeReport']);
        // Thống kê tổng quan về lương
        Route::get('/statistics', [PayrollController::class, 'statistics']);
    });

    // Two-Factor Authentication routes - Bảo mật 2 lớp
    Route::prefix('2fa')->group(function () {
        Route::get('/setup', [TwoFactorController::class, 'setup']);
        Route::post('/verify', [TwoFactorController::class, 'verify']);
        Route::post('/disable', [TwoFactorController::class, 'disable']);
        Route::get('/status', [TwoFactorController::class, 'status']);
    });
});
