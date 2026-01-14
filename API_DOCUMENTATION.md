# 📡 API DOCUMENTATION - Attendance System

## 🔗 Production URL
```
Base URL: https://YOUR_APP.railway.app/api
```

## 🔑 Authentication
Hầu hết endpoints yêu cầu Bearer Token (Sanctum).

### Login
```http
POST /login
Content-Type: application/json

{
  "email": "admin@demo.com",
  "password": "Admin@123"
}

Response:
{
  "status": "success",
  "data": {
    "access_token": "150|xxxxxx",
    "user": {
      "id": 1,
      "name": "Admin Demo",
      "email": "admin@demo.com",
      "role": "admin"
    }
  }
}
```

Sử dụng token:
```http
Authorization: Bearer 150|xxxxxx
```

---

## 👥 QUẢN LÝ USER (Admin only)

### Danh sách users
```http
GET /users
Authorization: Bearer {token}

Response:
{
  "status": "success",
  "data": [...]
}
```

### Tạo user mới
```http
POST /users
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Nguyen Van A",
  "email": "nguyenvana@company.com",
  "password": "Password@123",
  "role": "staff",
  "department_id": 1,
  "hourly_rate": 20000,
  "nfc_card_id": "ABC123456",
  "biometric_id": "BIO_001"
}
```

### Cập nhật user
```http
PUT /users/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Nguyen Van A (Updated)",
  "email": "nguyenvana@company.com",
  "role": "manager"
}
```

### Khóa/mở khóa user
```http
PUT /users/{id}/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "active"  // hoặc "inactive"
}
```

---

## 🕐 QUẢN LÝ CA LÀM VIỆC (Manager/Admin)

### Danh sách ca
```http
GET /shifts
Authorization: Bearer {token}

Response:
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Morning Shift",
      "start_time": "08:00:00",
      "end_time": "17:00:00",
      "code": "SHIFT_001",
      "latitude": 10.762622,
      "longitude": 106.660172,
      "radius": 100
    }
  ]
}
```

### Tạo ca mới
```http
POST /shifts
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Evening Shift",
  "start_time": "13:00:00",
  "end_time": "22:00:00",
  "latitude": 10.762622,
  "longitude": 106.660172,
  "radius": 100
}
```

---

## 📲 CHẤM CÔNG (Staff)

### 1. NFC Check-in (Kiosk - không cần auth)
```http
POST /kiosk/attendance
Content-Type: application/json

{
  "nfc_card_id": "ABC123456",
  "device_info": "Kiosk-01",
  "timezone": "Asia/Ho_Chi_Minh"
}

Response:
{
  "status": "success",
  "type": "check_in",
  "message": "Điểm danh thành công",
  "data": {
    "attendance_id": 123,
    "user": {...},
    "shift": {...},
    "check_in_time": "2026-01-14T08:05:30.000000Z"
  }
}
```

### 2. Face Recognition (Kiosk)
```http
POST /kiosk/attendance-face
Content-Type: application/json

{
  "user_id": 5,
  "device_info": "Kiosk-Face-01",
  "timezone": "Asia/Ho_Chi_Minh"
}
```

### 3. QR Kiosk Session (Staff quét QR)
**Bước 1: Kiosk tạo QR session**
```http
POST /kiosk/qr/session
Content-Type: application/json

{
  "kiosk_id": "KIOSK_01",
  "meta": {}
}

Response:
{
  "status": "success",
  "data": {
    "code": "QR_ABC123XYZ",
    "expires_at": "2026-01-14T08:06:30.000000Z",
    "kiosk_id": "KIOSK_01",
    "ttl_seconds": 60
  }
}
```

**Bước 2: Staff quét QR và gửi code**
```http
POST /attendance/qr
Authorization: Bearer {token}
Content-Type: application/json

{
  "qr_code": "QR_ABC123XYZ",
  "device_info": "Android",
  "latitude": 10.762622,
  "longitude": 106.660172,
  "timezone": "Asia/Ho_Chi_Minh"
}

Response:
{
  "status": "success",
  "type": "check_in",
  "message": "Điểm danh thành công",
  "data": {
    "attendance": {...},
    "shift": {...}
  }
}
```

### 4. Check-out (Staff)
```http
POST /attendance/checkout
Authorization: Bearer {token}
Content-Type: application/json

{
  "device_info": "Android",
  "latitude": 10.762622,
  "longitude": 106.660172,
  "timezone": "Asia/Ho_Chi_Minh"
}

Response:
{
  "status": "success",
  "message": "Check-out thành công",
  "data": {
    "attendance_id": 123,
    "check_in_time": "08:05:30",
    "check_out_time": "17:08:45",
    "work_hours": 9.05,
    "regular_hours": 8.0,
    "overtime_hours": 1.05,
    "earned_salary": 185000
  }
}
```

---

## 📊 BÁO CÁO & THỐNG KÊ

### Báo cáo tháng
```http
GET /reports/monthly?year=2026&month=1
Authorization: Bearer {token}

Response:
{
  "status": "success",
  "data": {
    "total_work_hours": 176.5,
    "total_overtime_hours": 12.3,
    "attendance_rate": 95.5,
    "daily_breakdown": [...]
  }
}
```

### Báo cáo tuần
```http
GET /reports/weekly?year=2026&week=2
Authorization: Bearer {token}
```

### Báo cáo lương tháng
```http
GET /payroll/report?year=2026&month=1
Authorization: Bearer {token}

Response:
{
  "status": "success",
  "data": [
    {
      "user_id": 5,
      "name": "Nguyen Van A",
      "total_work_hours": 176.5,
      "regular_hours": 160,
      "overtime_hours": 16.5,
      "earned_salary": 3530000
    }
  ]
}
```

### Gửi email báo cáo (Manager/Admin)
```http
POST /payroll/send-email
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 5,
  "year": 2026,
  "month": 1
}

Response:
{
  "status": "success",
  "message": "Đã gửi email báo cáo lương"
}
```

---

## 📝 ĐƠN XIN NGHỈ

### Danh sách đơn
```http
GET /leaves
Authorization: Bearer {token}

Response:
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "user": {...},
      "start_date": "2026-01-15",
      "end_date": "2026-01-16",
      "reason": "Nghỉ bệnh",
      "status": "pending"
    }
  ]
}
```

### Tạo đơn mới
```http
POST /leaves
Authorization: Bearer {token}
Content-Type: application/json

{
  "start_date": "2026-01-20",
  "end_date": "2026-01-21",
  "reason": "Nghỉ phép"
}
```

### Duyệt/từ chối đơn (Manager/Admin)
```http
PUT /leaves/{id}/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "approved",  // hoặc "rejected"
  "note": "OK"
}
```

---

## 🔒 BẢO MẬT & AUDIT

### Xem audit logs (Admin)
```http
GET /audit-logs?page=1&per_page=50
Authorization: Bearer {token}

Response:
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 100,
        "user": {...},
        "action": "create",
        "auditable_type": "App\\Models\\User",
        "auditable_id": 5,
        "old_values": null,
        "new_values": {...},
        "ip_address": "203.x.x.x",
        "user_agent": "Mozilla/5.0...",
        "created_at": "2026-01-14T08:30:00.000000Z"
      }
    ],
    "total": 500
  }
}
```

---

## 🏥 HEALTH CHECK

### Kiểm tra trạng thái hệ thống
```http
GET /kiosk/status

Response:
{
  "status": "ok",
  "database": "connected",
  "timestamp": "2026-01-14T10:30:00.000000Z"
}
```

---

## 📦 POSTMAN COLLECTION

Import file này vào Postman để test nhanh tất cả endpoints:
👉 [Download Postman Collection](./POSTMAN_COLLECTION.json)

Hoặc click [![Run in Postman](https://run.pstmn.io/button.svg)](https://god.gw.postman.com/run-collection/your-collection-id)

---

## 🔑 TÀI KHOẢN DEMO

| Role | Email | Password | Quyền |
|------|-------|----------|-------|
| Admin | admin@demo.com | Admin@123 | Toàn quyền |
| Manager | manager@demo.com | Manager@123 | Quản lý ca, báo cáo |
| Staff | staff1@demo.com | Staff@123 | Chấm công |
| Staff | staff2@demo.com | Staff@123 | Chấm công |
| Staff | staff3@demo.com | Staff@123 | Chấm công |

---

## ⚠️ RATE LIMITING

- `/login`: 5 requests/minute
- `/kiosk/*`: 10 requests/minute
- Các endpoint khác: 60 requests/minute

---

## 📞 SUPPORT

- Email: nguyenchithanh@example.com
- GitHub: https://github.com/your-username/attendance-api-backend
- Deploy: Railway.app

---

**Last updated:** 2026-01-14  
**Version:** 1.0.0  
**Laravel:** 9.x  
**PHP:** 8.0+
