<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo lương tháng {{ $month }}/{{ $year }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 30px 20px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
        }
        .summary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 25px;
            margin: 20px 0;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .summary h3 {
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .summary-label {
            font-weight: 500;
        }
        .summary-value {
            font-weight: bold;
            font-size: 18px;
        }
        .salary-highlight {
            background-color: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            text-align: center;
        }
        .salary-highlight .label {
            font-size: 14px;
            margin-bottom: 5px;
            opacity: 0.9;
        }
        .salary-highlight .amount {
            font-size: 32px;
            font-weight: bold;
        }
        .details-section {
            margin-top: 30px;
        }
        .details-section h3 {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th {
            background-color: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #f0f0f0;
        }
        .footer {
            background-color: #f8f9fa;
            text-align: center;
            padding: 30px 20px;
            color: #666;
            font-size: 14px;
            border-top: 3px solid #667eea;
        }
        .footer p {
            margin: 5px 0;
        }
        .footer strong {
            color: #667eea;
        }
        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 5px 0;
            color: #1976D2;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 BÁO CÁO LƯƠNG THÁNG {{ $month }}/{{ $year }}</h1>
            <p>Hệ thống chấm công NCT Attendance</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                Xin chào <strong>{{ $employeeData['user_name'] }}</strong>,
            </div>

            <p style="margin-bottom: 20px;">
                Đây là báo cáo tổng kết công việc và lương của bạn trong tháng {{ $month }}/{{ $year }}.
                Cảm ơn bạn đã cống hiến và làm việc chăm chỉ!
            </p>

            <!-- Summary Box -->
            <div class="summary">
                <h3>📌 TỔNG KẾT THÁNG {{ $month }}/{{ $year }}</h3>

                <div class="summary-item">
                    <span class="summary-label">👤 Họ tên:</span>
                    <span class="summary-value">{{ $employeeData['user_name'] }}</span>
                </div>

                <div class="summary-item">
                    <span class="summary-label">📧 Email:</span>
                    <span class="summary-value">{{ $employeeData['email'] }}</span>
                </div>

                <div class="summary-item">
                    <span class="summary-label">📅 Số ngày làm việc:</span>
                    <span class="summary-value">{{ $employeeData['total_days_worked'] }} ngày</span>
                </div>

                <div class="summary-item">
                    <span class="summary-label">⏰ Tổng giờ làm việc:</span>
                    <span class="summary-value">{{ $employeeData['total_work_hours'] }} giờ</span>
                </div>

                <div class="summary-item">
                    <span class="summary-label">💵 Lương/giờ:</span>
                    <span class="summary-value">{{ number_format($employeeData['hourly_rate'], 0, ',', '.') }} VNĐ</span>
                </div>

                <!-- Salary Highlight -->
                <div class="salary-highlight">
                    <div class="label">💰 TỔNG LƯƠNG DỰ KIẾN:</div>
                    <div class="amount">{{ number_format($employeeData['total_salary'], 0, ',', '.') }} VNĐ</div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <p><strong>📝 Lưu ý:</strong> Số liệu trên là tính toán tự động dựa trên dữ liệu chấm công.
                Nếu có sai sót, vui lòng liên hệ phòng nhân sự để được hỗ trợ.</p>
            </div>

            <!-- Details Table -->
            <div class="details-section">
                <h3>📅 CHI TIẾT CHẤM CÔNG</h3>

                @if(count($employeeData['details']) > 0)
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
                            <td>{{ $day['day_of_week'] }}</td>
                            <td>{{ $day['check_in'] }}</td>
                            <td>{{ $day['check_out'] }}</td>
                            <td><strong>{{ $day['work_hours'] }} giờ</strong></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <p style="text-align: center; color: #999; padding: 20px;">
                    Không có dữ liệu chấm công trong tháng này.
                </p>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>📧 Email tự động - Vui lòng không trả lời</strong></p>
            <p>Hệ thống chấm công NCT Attendance</p>
            <p>© {{ $year }} - Developed by <strong>Nguyen Chi Thanh</strong></p>
            <p style="margin-top: 10px; font-size: 12px;">
                Email này được gửi tự động từ hệ thống.
                Nếu bạn không phải là người nhận, vui lòng bỏ qua email này.
            </p>
        </div>
    </div>
</body>
</html>
