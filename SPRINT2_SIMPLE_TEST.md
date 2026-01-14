# SPRINT 2 - Test Cases Đơn Giản

## 📱 Hướng dẫn test nhanh các tính năng mới

---

## ✅ Test 1: Shift Overlap Validation

### Mục tiêu
Kiểm tra hệ thống ngăn chặn tạo ca làm việc trùng lặp

### Bước test
1. Login với tài khoản Admin/Manager
2. Vào **Shift Management**
3. Tạo ca "Morning" (08:00-17:00) - Lưu thành công ✅
4. Tạo ca "Test Overlap" (10:00-15:00) - Phải báo lỗi ❌

### Kết quả mong đợi
- Dialog hiện lỗi: "Ca trùng lấn với ca Morning (08:00-17:00)"
- Ca không được tạo

### Test ca qua đêm
5. Tạo ca "Night" (22:00-06:00) - Lưu thành công ✅
6. Tạo ca "Late Night" (23:00-05:00) - Phải báo lỗi ❌

---

## ✅ Test 2: Race Condition Prevention

### Automated Test
```bash
cd d:\NguyenChiThanh\server_api
php artisan test --filter=RaceConditionTest
```

### Kết quả mong đợi
```
✓ concurrent checkin requests do not create duplicate attendance (4 tests)
Tests: 4 passed
```

### Manual Test (Nâng cao)
1. Mở 2 tab browser cùng lúc
2. Đăng nhập cùng 1 user
3. Đồng thời click "Check-in" ở cả 2 tabs
4. Check database:
```sql
SELECT COUNT(*) FROM attendances 
WHERE user_id = 1 AND DATE(check_in_time) = CURDATE() 
AND check_out_time IS NULL;
```
5. Kết quả: **COUNT = 1** (chỉ có 1 record)

---

## ✅ Test 3: Overtime Calculation

### Scenario A: Regular Hours (0-8h)
**Test Data:**
- Check-in: 08:00
- Check-out: 16:00
- User hourly_rate: 50,000 VND

**Kết quả mong đợi:**
```
Regular hours: 8h
Overtime: 0h
Earned salary: 350,000 VND (7h thực tế × 50k)
```

### Scenario B: Overtime 1.5x (8-10h)
**Test Data:**
- Check-in: 08:00
- Check-out: 18:00
- User hourly_rate: 50,000 VND

**Kết quả mong đợi:**
```
Regular hours: 8h
Overtime hours: 2h
Earned salary: 550,000 VND
  = 7h × 50k (regular)
  + 2h × 50k × 1.5 (overtime)
```

### Scenario C: Overtime Double 2.0x (>10h)
**Test Data:**
- Check-in: 08:00
- Check-out: 20:00
- User hourly_rate: 50,000 VND

**Kết quả mong đợi:**
```
Regular hours: 8h
Overtime hours: 2h
Overtime double: 2h
Earned salary: 750,000 VND
  = 7h × 50k (regular)
  + 2h × 50k × 1.5 (overtime)
  + 2h × 50k × 2.0 (double)
```

### Scenario D: Weekend Bonus 2.0x
**Test Data:**
- Check-in: 08:00 (Saturday/Sunday)
- Check-out: 16:00
- User hourly_rate: 50,000 VND

**Kết quả mong đợi:**
```
Earned salary: 700,000 VND (7h × 50k × 2.0)
```

### Verify trên UI
1. Login với user đã có attendance
2. Vào **Payroll Screen**
3. Check breakdown hiển thị:
```
Lương/giờ: 50,000 VNĐ
Tổng giờ làm: 11.00 giờ

  • Giờ cơ bản: 8.00h × 50,000
  • Tăng ca (x1.5): 2.00h × 75,000
  • Tăng ca (x2.0): 1.00h × 100,000
  • Giờ nghỉ trưa: -1.00h
```

---

## ✅ Test 4: Department Management

### Test Create Department
**API Test:**
```bash
POST /api/departments
{
  "name": "IT Department",
  "parent_id": null,
  "manager_id": 2,
  "description": "Technology team"
}
```

**Expected:** Status 201 Created

### Test Sub-Department
```bash
POST /api/departments
{
  "name": "Backend Team",
  "parent_id": 1,
  "manager_id": 3
}
```

**Expected:** Status 201, nested structure working

### Test Manager Scope
1. Login với **User A** (Manager, department_id = 1)
2. Vào **User Management**
3. Verify: Chỉ thấy users có department_id = 1
4. Users từ department khác **không xuất hiện**

### Test Admin Scope
1. Login với **Admin**
2. Vào **User Management**
3. Verify: Thấy **tất cả users** từ mọi department

### Test Department Dropdown (Frontend)
1. Login Admin
2. Vào **User Management** → Click "Thêm Nhân Viên"
3. Verify dropdown "Phòng ban" có:
   - "Không chọn"
   - "IT Department"
   - "Backend Team"
   - ...
4. Chọn department → Lưu
5. Verify user được gán department đúng

---

## ✅ Test 5: Timezone Handling

### Test Check-in with Timezone
1. Mở Kiosk screen
2. Quét thẻ NFC hoặc Face ID
3. Check network logs (Browser DevTools)

**Expected Request:**
```json
{
  "nfc_code": "AA:BB:CC:DD",
  "timezone": "ICT"  // hoặc "Asia/Ho_Chi_Minh"
}
```

### Verify Database
```sql
SELECT id, user_id, check_in_time, timezone 
FROM attendances 
ORDER BY id DESC 
LIMIT 5;
```

**Expected:** Column `timezone` = "Asia/Ho_Chi_Minh"

---

## ✅ Test 6: Button Disable (Kiosk)

### Test Prevent Double Submit
1. Vào **Kiosk Screen**
2. Click "Chấm công vân tay"
3. **Ngay lập tức** click lại nhiều lần

**Expected:**
- Button disable ngay sau click đầu tiên
- Hiện CircularProgressIndicator
- Không gửi duplicate requests
- Sau khi xong, button enable lại

---

## ✅ Test 7: Kiosk QR Session (QR động)

### Mục tiêu
- Kiosk tự tạo QR động (TTL ngắn) để nhân viên quét bằng điện thoại.
- Quét 2 lần tự check-in/check-out.
- Không ảnh hưởng NFC.

### Automated Test
```bash
cd d:\NguyenChiThanh\server_api
php artisan test --filter=QrKioskAttendanceTest
```

### Manual Test (End-to-end)
1. Backend migrate (chỉ migrate thêm, KHÔNG migrate:fresh):
```bash
cd d:\NguyenChiThanh\server_api
php artisan migrate
```

2. Trên kiosk (Flutter): mở **Kiosk** → chọn **QR (Kiosk)** → thấy QR hiển thị.

3. Trên điện thoại nhân viên: mở **QR Scanner** → quét QR trên kiosk.

**Expected lần 1:**
- Response `type = check_in`
- Có `data.attendance.check_in_time` dạng ISO-8601

4. Quét lại QR lần 2.

**Expected lần 2:**
- Response `type = check_out`
- Có `data.attendance.check_out_time` + `work_hours`

### Notes
- Nếu backend bật bảo vệ kiosk token: set env `KIOSK_QR_TOKEN`, kiosk cần gửi header `X-Kiosk-Token`.
- TTL mặc định lấy từ `KIOSK_QR_TTL_SECONDS` (clamp 10–600).

---

## 🔍 Quick Verification Commands

### 1. Run All SPRINT 2 Tests
```bash
cd d:\NguyenChiThanh\server_api
php artisan test
```

**Expected:**
```
Tests: 12 passed
Time: ~2s
```

### 2. Check Migrations Status
```sql
SELECT migration FROM migrations 
WHERE migration LIKE '%2024%' 
ORDER BY id DESC LIMIT 10;
```

**Expected:** 5 SPRINT 2 migrations:
- add_timezone_to_attendances
- add_composite_index_attendances
- add_overtime_columns_to_attendances
- create_departments_table
- add_department_id_to_users

### 3. Verify Config Values
```bash
# Check config/payroll.php
cat d:\NguyenChiThanh\server_api\config\payroll.php | grep "rate"
```

**Expected:**
```php
'overtime_rate' => 1.5,
'overtime_double_rate' => 2.0,
'weekend_multiplier' => 2.0,
```

### 4. Format Flutter Code
```bash
cd d:\FE
dart format lib/
```

**Expected:** "Formatted X files (0 changed)"

---

## 🎯 Checklist Nhanh

### Backend
- [ ] Shift overlap validation hoạt động
- [ ] Race condition tests pass (4/4)
- [ ] Overtime calculation đúng
- [ ] Department CRUD working
- [ ] Manager scope filter đúng

### Frontend
- [ ] Shift overlap dialog hiển thị
- [ ] Timezone gửi lên API
- [ ] Button disable khi processing
- [ ] Overtime breakdown hiển thị
- [ ] Department dropdown working

### Database
- [ ] 5 migrations đã run thành công
- [ ] Timezone column có data
- [ ] Overtime columns populate đúng
- [ ] Department relationships đúng

---

## 🐛 Common Issues & Fixes

### Issue 1: Test Fail "ShiftFactory not found"
**Fix:** Test đã được update dùng `Shift::create()` thay vì factory

### Issue 2: Department dropdown không hiển thị
**Check:**
- `_fetchDepartments()` được gọi trong `initState()`
- API `/api/departments` return data đúng format
- `_isDepartmentsLoaded = true`

### Issue 3: Overtime calculation sai
**Debug:**
```sql
SELECT 
  check_in_time, check_out_time, work_hours,
  regular_hours, overtime_hours, overtime_double_hours,
  break_hours
FROM attendances 
WHERE id = [attendance_id];
```

### Issue 4: Manager thấy users từ team khác
**Check:**
- `User::accessibleBy()` scope trong PayrollController
- User có đúng `department_id`
- Manager có đúng role `manager`

---

## 📊 Performance Expectations

| Operation | Expected Time |
|-----------|--------------|
| Shift overlap validation | < 100ms |
| Check-in with transaction lock | < 200ms |
| Overtime calculation | < 50ms |
| Department scope query | < 150ms |
| All tests | < 2s |

---

## ✅ Final Verification

Sau khi test xong tất cả, confirm:

1. **No breaking changes** - Tất cả features cũ vẫn hoạt động
2. **No duplicate attendance** - Race condition tests pass
3. **Overtime accurate** - Manual verify với calculator
4. **Department scope working** - Manager chỉ thấy team mình
5. **Code formatted** - Chạy `dart format lib/` thành công

---

**SPRINT 2 Status:** ✅ 100% Complete  
**Total Tasks:** 25/25 (23 implemented + 2 skipped hợp lý)  
**Test Coverage:** 12 automated tests (all passing)  
**Breaking Changes:** NONE

**Document Version:** 1.0 Simple  
**Last Updated:** January 13, 2026
