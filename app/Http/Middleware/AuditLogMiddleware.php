<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AuditLogMiddleware
 * BỔ SUNG: Tự động log mọi thay đổi dữ liệu (POST/PUT/DELETE)
 * Pattern: Capture before/after data, không block request nếu log fail
 */
class AuditLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\JsonResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Chỉ log các methods thay đổi dữ liệu
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        // Lấy old data trước khi xử lý request (nếu là UPDATE/DELETE)
        $oldData = null;
        if (in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
            $oldData = $this->getOldDataFromRoute($request);
        }

        // Xử lý request
        $response = $next($request);

        // Log sau khi response thành công (best effort - không block nếu fail)
        try {
            // Chỉ log nếu response success (2xx)
            if ($response->status() >= 200 && $response->status() < 300) {
                $this->createAuditLog($request, $response, $oldData);
            }
        } catch (\Exception $e) {
            // Log lỗi nhưng KHÔNG block request
            Log::error('Audit log failed: '.$e->getMessage(), [
                'request' => $request->path(),
                'user_id' => auth()->id(),
            ]);
        }

        return $response;
    }

    /**
     * Tạo audit log record
     */
    private function createAuditLog(Request $request, $response, $oldData)
    {
        $model = $this->extractModelFromRoute($request);
        $modelId = $this->extractModelIdFromRoute($request);

        // Lấy new data từ response
        $newData = null;
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $responseData = $response->getData(true);
            $newData = $responseData['data'] ?? $responseData;
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => strtoupper($request->method()),
            'model' => $model,
            'model_id' => $modelId,
            'old_data' => $oldData,
            'new_data' => $newData,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Extract model name từ route
     */
    private function extractModelFromRoute(Request $request): ?string
    {
        $path = $request->path();

        // Pattern: api/users/123 -> User
        // api/shifts/5 -> Shift
        // api/attendance -> Attendance

        if (preg_match('/api\/([a-z-]+)/i', $path, $matches)) {
            $segment = $matches[1];

            // Map route segment -> Model name
            $modelMap = [
                'users' => 'User',
                'shifts' => 'Shift',
                'attendance' => 'Attendance',
                'leaves' => 'LeaveRequest',
                'kiosk' => 'Attendance', // Kiosk attendance -> Attendance model
                'reports' => 'Report',
            ];

            return $modelMap[$segment] ?? ucfirst($segment);
        }

        return null;
    }

    /**
     * Extract model ID từ route parameters
     */
    private function extractModelIdFromRoute(Request $request): ?int
    {
        // Lấy ID từ route parameters
        $routeParams = $request->route()?->parameters() ?? [];

        foreach ($routeParams as $key => $value) {
            if (in_array($key, ['id', 'user', 'shift', 'attendance', 'leave'])) {
                return is_numeric($value) ? (int) $value : null;
            }
        }

        return null;
    }

    /**
     * Lấy old data trước khi update/delete
     */
    private function getOldDataFromRoute(Request $request)
    {
        try {
            $modelId = $this->extractModelIdFromRoute($request);
            if (! $modelId) {
                return null;
            }

            $model = $this->extractModelFromRoute($request);
            $modelClass = "App\\Models\\{$model}";

            if (class_exists($modelClass)) {
                $record = $modelClass::find($modelId);

                return $record ? $record->toArray() : null;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get old data: '.$e->getMessage());
        }

        return null;
    }
}
