# NFC – Checklist nghiệm thu (trung tâm đề tài)

File mẹ: server_api (backend Laravel) + FE (Flutter)

## 1) Luồng phát hành nội dung thẻ (NDEF payload)
- API: `POST /api/users/{id}/nfc/issue` (Admin)
- Kỳ vọng:
  - Trả về `data.payload` dạng: `NCTNFC:v1:<user_id>:<token>`
  - Backend chỉ lưu `nfc_token_hash` (hash SHA-256), không lưu token plain.

## 2) Luồng ghi thẻ NDEF trên app (Admin)
- FE: màn quản lý nhân viên → chọn `Ghi thẻ (NDEF)`
- Kỳ vọng:
  - App gọi `issue` lấy `payload`
  - App ghi NDEF Text record chứa payload
  - App báo thành công/ lỗi rõ ràng

## 3) Luồng đọc thẻ & chấm công tại Kiosk (không cần auth)
- API: `POST /api/kiosk/attendance` body `{ nfc_code }`
- `nfc_code` ưu tiên:
  1) NDEF payload (NCTNFC:v1:...)
  2) Fallback UID dạng `AA:BB:...` (legacy)

Kịch bản nghiệm thu:
- Quẹt thẻ lần 1 → `type=check_in` + có `data.check_in_time`
- Quẹt thẻ lần 2 → `type=check_out` + có `data.check_out_time` + `data.work_hours`
- Thẻ không hợp lệ → 404 + message rõ ràng
- User bị khóa (`is_active=false`) → 403

## 4) Tính giờ làm & lương (liên quan trực tiếp NFC)
- Kỳ vọng:
  - `work_hours` tính theo phút: `diffInMinutes/60` (không làm tròn theo giờ nguyên)
  - Lương ca = `work_hours * hourly_rate`

## 5) Robustness / Edge cases
- Payload có whitespace / ký tự null (NDEF) vẫn parse được
- Nếu bật TTL (ENV `NFC_PAYLOAD_TTL_DAYS>0`): payload quá hạn phải bị từ chối

## 6) Test tự động (không dùng migrate:fresh)
- Unit:
  - tests/Unit/NfcPayloadServiceTest.php
- Feature:
  - tests/Feature/NfcAttendanceFlowTest.php
  - tests/Feature/NfcAdminAccessTest.php

Chạy test:
- `vendor\\bin\\phpunit --testsuite Unit --testsuite Feature`
