<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meta['title'] ?? 'Báo cáo theo kỳ' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { font-size: 24px; margin-bottom: 10px; }
        .content { padding: 30px 20px; }
        .summary { background: #f8f9fa; padding: 18px; border-left: 4px solid #667eea; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th { background-color: #667eea; color: #fff; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #e0e0e0; }
        .footer { background-color: #f8f9fa; text-align: center; padding: 20px; color: #666; font-size: 13px; border-top: 3px solid #667eea; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 {{ $meta['title'] ?? 'BÁO CÁO THEO KỲ' }}</h1>
        <div>{{ $meta['subtitle'] ?? 'Hệ thống chấm công NCT Attendance' }}</div>
    </div>

    <div class="content">
        <p>Xin chào <strong>{{ $employeeData['user_name'] ?? '' }}</strong>,</p>
        <p style="margin-top: 10px;">Đây là báo cáo tổng kết công việc và lương của bạn trong kỳ <strong>{{ $meta['range'] ?? '' }}</strong>.</p>

        <div class="summary">
            <div><strong>👤 Họ tên:</strong> {{ $employeeData['user_name'] ?? '' }}</div>
            <div><strong>📧 Email:</strong> {{ $employeeData['email'] ?? '' }}</div>
            <div><strong>📅 Số ngày làm việc:</strong> {{ $employeeData['total_days_worked'] ?? 0 }} ngày</div>
            <div><strong>⏰ Tổng giờ làm:</strong> {{ $employeeData['total_work_hours'] ?? 0 }} giờ</div>
            <div><strong>💵 Lương/giờ:</strong> {{ number_format($employeeData['hourly_rate'] ?? 0, 0, ',', '.') }} VNĐ</div>
            <div style="margin-top: 8px;"><strong>💰 Tổng lương dự kiến:</strong> {{ number_format($employeeData['total_salary'] ?? 0, 0, ',', '.') }} VNĐ</div>
        </div>

        @if(isset($employeeData['details']) && count($employeeData['details']) > 0)
            <table>
                <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Thứ</th>
                    <th>Giờ Vào</th>
                    <th>Giờ Ra</th>
                    <th>Tổng Giờ</th>
                </tr>
                </thead>
                <tbody>
                @foreach($employeeData['details'] as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td>{{ $day['day_of_week'] ?? '' }}</td>
                        <td>{{ $day['check_in'] }}</td>
                        <td>{{ $day['check_out'] }}</td>
                        <td><strong>{{ $day['work_hours'] }} giờ</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p style="text-align:center; padding: 16px; color:#999;">Không có dữ liệu chấm công trong kỳ này.</p>
        @endif
    </div>

    <div class="footer">
        <p><strong>📧 Email tự động - Vui lòng không trả lời</strong></p>
        <p>NCT Attendance</p>
        <p>© {{ date('Y') }}</p>
    </div>
</div>
</body>
</html>
