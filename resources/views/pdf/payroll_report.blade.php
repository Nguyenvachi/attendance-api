<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['title'] ?? 'Báo cáo lương' }}</title>
    <style>
        @page { margin: 18px 18px 64px 18px; }

        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111827; }
        .muted { color: #6b7280; }
        .small { font-size: 10px; }
        .right { text-align: right; }
        .center { text-align: center; }

        .header { border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 10px; }
        .company { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .dept { font-size: 10px; color: #374151; margin-top: 2px; }
        .doc-meta { text-align: right; font-size: 10px; color: #374151; }
        .title { font-size: 16px; font-weight: 800; margin: 8px 0 2px 0; letter-spacing: 0.2px; }
        .subtitle { font-size: 11px; color: #374151; margin: 0; }

        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; margin-top: 10px; }
        .box-title { font-weight: 700; margin: 0 0 6px 0; font-size: 12px; }

        .cards { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
        .card-title { font-size: 10px; color: #6b7280; margin: 0 0 2px 0; }
        .card-value { font-size: 14px; font-weight: 800; margin: 0; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th, table.data td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        table.data th { background: #f3f4f6; text-align: left; font-size: 10px; }
        table.data tbody tr:nth-child(even) { background: #fafafa; }
        .nowrap { white-space: nowrap; }

        .footer { position: fixed; left: 18px; right: 18px; bottom: 18px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 10px; color: #6b7280; }
        .footer .left { float: left; }
        .footer .right { float: right; }
        .page-number:before { content: counter(page); }

        .sign { margin-top: 14px; }
        .sign-table { width: 100%; border-collapse: collapse; }
        .sign-table td { width: 33.33%; text-align: center; vertical-align: top; padding-top: 6px; }
        .sign-role { font-weight: 700; }
        .sign-note { margin-top: 38px; border-top: 1px dotted #9ca3af; display: inline-block; width: 70%; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:top;">
                    <div class="company">CÔNG TY: NGUYỄN CHÍ THANH</div>
                    <div class="dept">PHÒNG/ĐƠN VỊ: KẾ TOÁN - NHÂN SỰ</div>
                </td>
                <td class="doc-meta" style="vertical-align:top;">
                    <div>Mẫu: HR-PAY-01</div>
                    <div>Ngày xuất: {{ $meta['generated_at'] ?? '' }}</div>
                </td>
            </tr>
        </table>

        <div class="title">{{ $meta['title'] ?? 'BÁO CÁO LƯƠNG TỔNG HỢP' }}</div>
        <div class="subtitle">
            Kỳ: <strong>{{ $meta['period'] ?? '' }}</strong>
            @if(!empty($meta['start_date']) && !empty($meta['end_date']))
                • Từ {{ $meta['start_date'] }} đến {{ $meta['end_date'] }}
            @endif
        </div>
    </div>

    @php
        $totalEmployees = (int)($data['total_employees'] ?? 0);
        $totalSalaryAll = (float)($data['total_salary_all'] ?? 0);
    @endphp

    <table class="cards">
        <tr>
            <td style="padding-right:8px;">
                <div class="card">
                    <div class="card-title">Tổng nhân viên</div>
                    <div class="card-value">{{ $totalEmployees }}</div>
                </div>
            </td>
            <td style="padding-left:8px;">
                <div class="card">
                    <div class="card-title">Tổng lương toàn bộ</div>
                    <div class="card-value">{{ number_format($totalSalaryAll, 0, ',', '.') }} đ</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="box">
        <div class="box-title">Bảng lương chi tiết</div>
        <div class="small muted">Ghi chú: Dữ liệu tổng hợp theo giờ công và lương/giờ của từng nhân viên.</div>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 6%">ID</th>
                <th style="width: 16%">Tên</th>
                <th style="width: 18%">Email</th>
                <th style="width: 10%">SĐT</th>
                <th style="width: 10%" class="right nowrap">Lương/giờ</th>
                <th style="width: 10%" class="center">Ngày công</th>
                <th style="width: 10%" class="right nowrap">Tổng giờ</th>
                <th style="width: 12%" class="right nowrap">Tổng lương</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($data['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['user_id'] ?? '' }}</td>
                    <td>{{ $row['user_name'] ?? '' }}</td>
                    <td>{{ $row['email'] ?? '' }}</td>
                    <td>{{ $row['phone'] ?? '' }}</td>
                    <td class="right">{{ number_format((float)($row['hourly_rate'] ?? 0), 0, ',', '.') }}</td>
                    <td class="center">{{ (int)($row['total_days_worked'] ?? 0) }}</td>
                    <td class="right">{{ number_format((float)($row['total_work_hours'] ?? 0), 2, ',', '.') }}</td>
                    <td class="right">{{ number_format((float)($row['total_salary'] ?? 0), 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center muted">Không có dữ liệu trong kỳ này</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="sign">
        <table class="sign-table">
            <tr>
                <td>
                    <div class="sign-role">Người lập</div>
                    <div class="muted small">(Ký, ghi rõ họ tên)</div>
                    <div class="sign-note"></div>
                </td>
                <td>
                    <div class="sign-role">Kế toán</div>
                    <div class="muted small">(Ký, ghi rõ họ tên)</div>
                    <div class="sign-note"></div>
                </td>
                <td>
                    <div class="sign-role">Giám đốc</div>
                    <div class="muted small">(Ký, ghi rõ họ tên)</div>
                    <div class="sign-note"></div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <div class="left">Tài liệu nội bộ • HR-PAY-01</div>
        <div class="right">Trang <span class="page-number"></span></div>
    </div>
</body>
</html>
