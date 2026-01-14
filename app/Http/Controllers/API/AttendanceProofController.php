<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceProof;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceProofController extends Controller
{
    private function ensurePublicCopy(string $relativePublicDiskPath): void
    {
        $p = ltrim($relativePublicDiskPath, '/');
        if ($p === '') {
            return;
        }

        // Nơi lưu thực tế của disk "public": storage/app/public/<path>
        $source = storage_path('app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $p));
        if (! is_file($source)) {
            return;
        }

        // Public URL mặc định của Laravel là /storage/<path>. Trên Windows đôi khi symlink không hoạt động,
        // nên tạo bản copy vật lý vào public/storage để luôn truy cập được.
        $target = public_path('storage'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $p));
        if (is_file($target)) {
            return;
        }

        $dir = dirname($target);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @copy($source, $target);
    }

    private function absolutePublicUrl(Request $request, string $publicPathOrUrl): string
    {
        $u = trim($publicPathOrUrl);

        if ($u === '') {
            return $u;
        }

        // Nếu đã là absolute URL thì trả thẳng.
        if (preg_match('#^https?://#i', $u) === 1) {
            return $u;
        }

        $base = $request->getSchemeAndHttpHost();

        if (str_starts_with($u, '/')) {
            return $base.$u;
        }

        return $base.'/'.ltrim($u, '/');
    }

    private function storageUrlFromPath(Request $request, string $publicDiskPath): string
    {
        $p = ltrim($publicDiskPath, '/');
        if ($p === '') {
            return '';
        }

        // Không dùng Storage::url() vì có thể bị ảnh hưởng bởi APP_URL=localhost.
        return $request->getSchemeAndHttpHost().'/storage/'.$p;
    }

    /**
     * POST /api/kiosk/attendance-proof
     * Upload ảnh xác thực từ Kiosk (không liên quan NFC parsing).
     * Bảo vệ bằng header X-Kiosk-Token (env KIOSK_PROOF_TOKEN) để tránh spam.
     */
    public function kioskUpload(Request $request)
    {
        $tokenExpected = (string) env('KIOSK_PROOF_TOKEN', '');
        if ($tokenExpected !== '') {
            $token = (string) $request->header('X-Kiosk-Token', '');
            if (! hash_equals($tokenExpected, $token)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kiosk token không hợp lệ',
                ], 403);
            }
        }

        // BỔ SUNG: File upload validation (SPRINT 1 Security)
        $validated = $request->validate([
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png',
                'max:5120', // 5MB
                function ($attribute, $value, $fail) {
                    // Verify thực sự là ảnh (không chỉ dựa vào extension)
                    if (! @getimagesize($value->getRealPath())) {
                        $fail('File không phải ảnh hợp lệ.');
                    }
                },
            ],
            'method' => 'required|string|max:50',
            'action' => 'required|string|max:50',
            'captured_at' => 'nullable|date',
            'user_id' => 'nullable|integer',
            'attendance_id' => 'nullable|integer',
            'meta' => 'nullable',
        ]);

        $capturedAt = null;
        if (! empty($validated['captured_at'])) {
            $capturedAt = Carbon::parse($validated['captured_at']);
        }

        // BỔ SUNG: Random filename để tránh path traversal attack (SPRINT 1 Security)
        $extension = $request->file('image')->getClientOriginalExtension();
        $filename = \Illuminate\Support\Str::random(40).'.'.$extension;
        $day = ($capturedAt ?? now())->format('Y-m-d');
        $userId = $validated['user_id'] ?? 'guest';
        $path = $request->file('image')->storeAs(
            "attendance_proofs/{$userId}/{$day}",
            $filename,
            'public'
        );

        // Đảm bảo ảnh có thể truy cập qua /storage/... kể cả khi storage:link không hoạt động.
        $this->ensurePublicCopy($path);

        $meta = null;
        if ($request->has('meta')) {
            // meta có thể là json string hoặc object (multipart)
            $raw = $request->input('meta');
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $meta = is_array($decoded) ? $decoded : ['raw' => $raw];
            } elseif (is_array($raw)) {
                $meta = $raw;
            }
        }

        // BỔ SUNG: fail-safe infer user_id/attendance_id từ meta nếu client không gửi top-level fields.
        $metaUserId = null;
        $metaAttendanceId = null;
        $metaUserName = null;
        if (is_array($meta)) {
            $metaUserId = data_get($meta, 'user_id');
            $metaAttendanceId = data_get($meta, 'attendance_id');
            $metaUserName = data_get($meta, 'user_name');
        }

        $userId = $validated['user_id'] ?? null;
        if (is_null($userId) && (is_int($metaUserId) || is_string($metaUserId)) && is_numeric($metaUserId)) {
            $userId = (int) $metaUserId;
        }

        $attendanceId = $validated['attendance_id'] ?? null;
        if (is_null($attendanceId) && (is_int($metaAttendanceId) || is_string($metaAttendanceId)) && is_numeric($metaAttendanceId)) {
            $attendanceId = (int) $metaAttendanceId;
        }

        // Nếu có user_name trong meta thì giữ lại để UI có thể fallback hiển thị.
        if (is_array($meta) && ! empty($metaUserName) && ! isset($meta['user_name'])) {
            $meta['user_name'] = $metaUserName;
        }

        $proof = AttendanceProof::create([
            'user_id' => $userId,
            'attendance_id' => $attendanceId,
            'method' => $validated['method'],
            'action' => $validated['action'],
            'captured_at' => $capturedAt,
            'image_path' => $path,
            'meta' => $meta,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Upload ảnh xác thực thành công',
            'data' => [
                'id' => $proof->id,
                'image_url' => $this->storageUrlFromPath($request, $proof->image_path),
                'captured_at' => optional($proof->captured_at)->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /api/attendance-proofs (admin)
     */
    public function index(Request $request)
    {
        $query = AttendanceProof::query()->with(['user']);

        $baseUrl = $request->getSchemeAndHttpHost();

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('date')) {
            $date = Carbon::parse($request->input('date'))->toDateString();
            $query->whereDate('captured_at', $date);
        }

        $perPage = (int) $request->input('per_page', 20);
        $items = $query->orderByDesc('id')->paginate(max(1, min(100, $perPage)));

        $items->getCollection()->transform(function (AttendanceProof $p) use ($baseUrl) {
            $this->ensurePublicCopy($p->image_path);
            $p2 = ltrim((string) $p->image_path, '/');
            $u = $p2 === '' ? '' : ($baseUrl.'/storage/'.$p2);

            $fallbackUserId = null;
            $fallbackUserName = null;
            if (is_array($p->meta)) {
                $fallbackUserId = data_get($p->meta, 'user_id');
                $fallbackUserName = data_get($p->meta, 'user_name');
            }

            $userName = optional($p->user)->name;
            if ((is_null($userName) || trim((string) $userName) === '') && ! empty($fallbackUserName)) {
                $userName = (string) $fallbackUserName;
            }

            $userId = $p->user_id;
            if (is_null($userId) && (is_int($fallbackUserId) || is_string($fallbackUserId)) && is_numeric($fallbackUserId)) {
                $userId = (int) $fallbackUserId;
            }

            return [
                'id' => $p->id,
                'user_id' => $userId,
                'user_name' => $userName,
                'attendance_id' => $p->attendance_id,
                'method' => $p->method,
                'action' => $p->action,
                'captured_at' => optional($p->captured_at)->toIso8601String(),
                'image_url' => $u,
                'meta' => $p->meta,
                'created_at' => optional($p->created_at)->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    /**
     * GET /api/attendance-proofs/{id} (admin)
     */
    public function show(Request $request, $id)
    {
        $p = AttendanceProof::with(['user'])->findOrFail($id);

        $this->ensurePublicCopy($p->image_path);

        $imageUrl = $this->storageUrlFromPath($request, $p->image_path);

        $fallbackUserId = null;
        $fallbackUserName = null;
        if (is_array($p->meta)) {
            $fallbackUserId = data_get($p->meta, 'user_id');
            $fallbackUserName = data_get($p->meta, 'user_name');
        }

        $userName = optional($p->user)->name;
        if ((is_null($userName) || trim((string) $userName) === '') && ! empty($fallbackUserName)) {
            $userName = (string) $fallbackUserName;
        }

        $userId = $p->user_id;
        if (is_null($userId) && (is_int($fallbackUserId) || is_string($fallbackUserId)) && is_numeric($fallbackUserId)) {
            $userId = (int) $fallbackUserId;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $p->id,
                'user_id' => $userId,
                'user_name' => $userName,
                'attendance_id' => $p->attendance_id,
                'method' => $p->method,
                'action' => $p->action,
                'captured_at' => optional($p->captured_at)->toIso8601String(),
                'image_url' => $imageUrl,
                'meta' => $p->meta,
                'created_at' => optional($p->created_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/attendance-proofs/stream (admin)
     * Realtime server-client (SSE) để Admin tự refresh khi có proof mới.
     *
     * Lưu ý: endpoint này KHÔNG thay đổi luồng NFC; chỉ đọc DB và stream event.
     */
    public function stream(Request $request)
    {
        $sinceId = (int) $request->query('since_id', 0);
        $timeoutSeconds = (int) $request->query('timeout', 60);

        $timeoutSeconds = max(10, min(300, $timeoutSeconds));

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        $baseUrl = $request->getSchemeAndHttpHost();

        return response()->stream(function () use ($sinceId, $timeoutSeconds, $baseUrl) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            @ini_set('implicit_flush', '1');
            @set_time_limit(0);

            $lastId = $sinceId;
            $startedAt = microtime(true);
            $lastPingAt = 0.0;

            while (microtime(true) - $startedAt < $timeoutSeconds) {
                if (connection_aborted()) {
                    break;
                }

                $items = AttendanceProof::query()
                    ->with(['user'])
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit(50)
                    ->get();

                foreach ($items as $p) {
                    $lastId = max($lastId, (int) $p->id);

                    $this->ensurePublicCopy($p->image_path);
                    $p2 = ltrim((string) $p->image_path, '/');
                    $u = $p2 === '' ? '' : ($baseUrl.'/storage/'.$p2);

                    $payload = [
                        'id' => $p->id,
                        'user_id' => $p->user_id,
                        'user_name' => optional($p->user)->name,
                        'attendance_id' => $p->attendance_id,
                        'method' => $p->method,
                        'action' => $p->action,
                        'captured_at' => optional($p->captured_at)->toIso8601String(),
                        'image_url' => $u,
                        'meta' => $p->meta,
                        'created_at' => optional($p->created_at)->toIso8601String(),
                    ];

                    echo "event: attendance_proof\n";
                    echo "id: {$p->id}\n";
                    echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                    @ob_flush();
                    @flush();
                }

                $now = microtime(true);
                if ($now - $lastPingAt >= 10) {
                    $lastPingAt = $now;
                    echo "event: ping\n";
                    echo "data: {}\n\n";
                    @ob_flush();
                    @flush();
                }

                usleep(1_000_000);
            }
        }, 200, $headers);
    }
}
