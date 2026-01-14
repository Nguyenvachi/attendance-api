<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['title'] ?? 'Báo cáo thống kê' }}</title>
    <style>
        @page { margin: 22px 22px 64px 22px; }

        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #111827; }
        .muted { color: #6b7280; }
        .small { font-size: 10px; }
        .right { text-align: right; }
        .center { text-align: center; }

        .header { border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 10px; }
        .company { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .dept { font-size: 10px; color: #374151; margin-top: 2px; }
        .doc-meta { text-align: right; font-size: 10px; color: #374151; }
        .title { font-size: 18px; font-weight: 800; margin: 8px 0 2px 0; letter-spacing: 0.2px; }
        .subtitle { font-size: 11px; color: #374151; margin: 0; }

        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; margin-top: 10px; }
        .box-title { font-weight: 700; margin: 0 0 6px 0; font-size: 12px; }
        .meta-grid { width: 100%; border-collapse: collapse; }
        .meta-grid td { padding: 2px 0; vertical-align: top; }
        .meta-label { width: 120px; color: #6b7280; }

        .cards { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
        .card-title { font-size: 10px; color: #6b7280; margin: 0 0 2px 0; }
        .card-value { font-size: 16px; font-weight: 800; margin: 0; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-warn { background: #fee2e2; color: #991b1b; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th, table.data td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        table.data th { background: #f3f4f6; text-align: left; font-size: 11px; }
        table.data tbody tr:nth-child(even) { background: #fafafa; }

        .footer { position: fixed; left: 22px; right: 22px; bottom: 18px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 10px; color: #6b7280; }
        .footer .left { float: left; }
        .footer .right { float: right; }
        .page-number:before { content: counter(page); }

        .sign { margin-top: 18px; }
        .sign-table { width: 100%; border-collapse: collapse; }
        .sign-table td { width: 33.33%; text-align: center; vertical-align: top; padding-top: 6px; }
        .sign-role { font-weight: 700; }
        .sign-note { margin-top: 42px; border-top: 1px dotted #9ca3af; display: inline-block; width: 70%; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:top;">
                    <div class="company">CÔNG TY: NGUYỄN CHÍ THANH</div>
                    <div class="dept">PHÒNG/ĐƠN VỊ: HÀNH CHÍNH - NHÂN SỰ</div>
                </td>
                <td class="doc-meta" style="vertical-align:top;">
                    <div>Mẫu: HR-ATT-01</div>
                    <div>Ngày xuất: {{ $meta['generated_at'] ?? '' }}</div>
                </td>
            </tr>
        </table>

        <div class="title">{{ $meta['title'] ?? 'BÁO CÁO THỐNG KÊ CHẤM CÔNG' }}</div>
        <div class="subtitle">
            Kỳ: <strong>{{ $meta['period'] ?? '' }}</strong>
            @if(!empty($meta['start_date']) && !empty($meta['end_date']))
                • Từ {{ $meta['start_date'] }} đến {{ $meta['end_date'] }}
            @endif
        </div>
    </div>

    <div class="box">
        <div class="box-title">Thông tin nhân viên</div>
        <table class="meta-grid">
            <tr>
                <td class="meta-label">Họ và tên:</td>
                <td><strong>{{ $user->name }}</strong></td>
                <td class="meta-label">Mã NV:</td>
                <td><strong>{{ $user->id }}</strong></td>
            </tr>
            <tr>
                <td class="meta-label">Email:</td>
                <td>{{ $user->email ?? '' }}</td>
                <td class="meta-label">SĐT:</td>
                <td>{{ $user->phone ?? '' }}</td>
            </tr>
        </table>
    </div>

    @php
        $totalWorkDays = (int)($data['total_work_days'] ?? 0);
        $lateDays = (int)($data['late_days'] ?? 0);
        $lateRate = $totalWorkDays > 0 ? round(($lateDays / $totalWorkDays) * 100, 1) : 0;
    @endphp

    <table class="cards">
        <tr>
            <td style="padding-right:8px;">
                <div class="card">
                    <div class="card-title">Tổng ngày làm</div>
                    <div class="card-value">{{ $totalWorkDays }}</div>
                </div>
            </td>
            <td style="padding:0 8px;">
                <div class="card">
                    <div class="card-title">Số ngày đi trễ</div>
                    <div class="card-value">{{ $lateDays }}</div>
                </div>
            </td>
            <td style="padding-left:8px;">
                <div class="card">
                    <div class="card-title">Tỷ lệ đi trễ</div>
                    <div class="card-value">{{ $lateRate }}%</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="box" style="margin-top:10px;">
        <div class="box-title">Chi tiết chấm công</div>
        <div class="small muted">Ghi chú: Đi trễ được xác định khi giờ vào lớn hơn giờ bắt đầu ca.</div>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 14%">Ngày</th>
                <th style="width: 14%">Giờ vào</th>
                <th style="width: 14%">Giờ bắt đầu ca</th>
                <th style="width: 12%" class="center">Đi trễ</th>
                <th style="width: 16%" class="right">Số phút trễ</th>
                <th>Ghi chú</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($data['details'] ?? []) as $item)
                <tr>
                    <td>{{ $item['date'] ?? '' }}</td>
                    <td>{{ $item['check_in'] ?? '' }}</td>
                    <td>{{ $item['shift_start'] ?? '' }}</td>
                    <td class="center">
                        @if(!empty($item['is_late']))
                            <span class="badge badge-warn">Có</span>
                        @else
                            <span class="badge badge-ok">Không</span>
                        @endif
                    </td>
                    <td class="right">{{ (int)($item['late_minutes'] ?? 0) }}</td>
                    <td class="muted"></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center muted">Không có dữ liệu trong kỳ này</td>
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
                    <div class="sign-role">Trưởng bộ phận</div>
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
        <div class="left">Tài liệu nội bộ • HR-ATT-01</div>
        <div class="right">Trang <span class="page-number"></span></div>
    </div>
</body>
</html>
