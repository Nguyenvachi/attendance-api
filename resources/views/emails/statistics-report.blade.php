<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meta['title'] ?? 'Báo cáo thống kê' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; }
        .header { background: linear-gradient(135deg, #4CAF50 0%, #2196F3 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { font-size: 24px; margin-bottom: 8px; }
        .content { padding: 30px 20px; }
        .summary { background: #e8f5e9; padding: 20px; border-left: 4px solid #4CAF50; margin: 15px 0; border-radius: 4px; }
        .summary-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ddd; }
        .summary-item:last-child { border: none; }
        .highlight { background: #fff9c4; padding: 12px; margin: 10px 0; border-radius: 4px; text-align: center; font-size: 18px; font-weight: bold; color: #f57c00; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th { background: #4CAF50; color: #fff; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #e0e0e0; }
        tr:nth-child(even) { background: #f9f9f9; }
        .late { color: #d32f2f; font-weight: bold; }
        .footer { background: #f8f9fa; text-align: center; padding: 20px; color: #666; font-size: 13px; border-top: 3px solid #4CAF50; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 {{ $meta['title'] ?? 'BÁO CÁO THỐNG KÊ' }}</h1>
        <div>{{ $meta['subtitle'] ?? 'Hệ thống chấm công NCT Attendance' }}</div>
    </div>

    <div class="content">
        <p>Xin chào <strong>{{ $meta['user_name'] ?? '' }}</strong>,</p>
        <p style="margin: 10px 0;">Đây là báo cáo thống kê chấm công của bạn trong kỳ <strong>{{ $meta['range'] ?? '' }}</strong>.</p>

        <div class="summary">
            <div class="summary-item">
                <span>📅 Tổng số ngày làm việc:</span>
                <strong>{{ $reportData['total_work_days'] ?? 0 }} ngày</strong>
            </div>
            <div class="summary-item">
                <span>⏰ Số ngày đi trễ:</span>
                <strong class="late">{{ $reportData['late_days'] ?? 0 }} ngày</strong>
            </div>
            @if(isset($reportData['total_work_days']) && $reportData['total_work_days'] > 0)
            <div class="summary-item">
                <span>📊 Tỉ lệ đúng giờ:</span>
                <strong>{{ round((($reportData['total_work_days'] - $reportData['late_days']) / $reportData['total_work_days']) * 100, 1) }}%</strong>
            </div>
            @endif
        </div>

        @if($reportData['late_days'] > 0)
            <div class="highlight">
                ⚠️ Bạn đã đi trễ {{ $reportData['late_days'] }} lần trong kỳ này!
            </div>
        @else
            <div style="background: #c8e6c9; padding: 12px; margin: 10px 0; border-radius: 4px; text-align: center; color: #2e7d32;">
                ✅ Xuất sắc! Bạn không đi trễ lần nào!
            </div>
        @endif

        @if(isset($reportData['details']) && count($reportData['details']) > 0)
            <h3 style="margin-top: 20px; color: #4CAF50;">📅 Chi tiết chấm công</h3>
            <table>
                <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Giờ Vào</th>
                    <th>Ca Bắt Đầu</th>
                    <th>Trạng Thái</th>
                    <th>Phút Trễ</th>
                </tr>
                </thead>
                <tbody>
                @foreach($reportData['details'] as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td>{{ $day['check_in'] }}</td>
                        <td>{{ $day['shift_start'] ?? 'N/A' }}</td>
                        <td>
                            @if($day['is_late'])
                                <span class="late">❌ Trễ</span>
                            @else
                                <span style="color: #4CAF50;">✅ Đúng giờ</span>
                            @endif
                        </td>
                        <td>{{ $day['late_minutes'] > 0 ? $day['late_minutes'] . ' phút' : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p style="text-align: center; padding: 16px; color: #999;">Không có dữ liệu chấm công trong kỳ này.</p>
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
