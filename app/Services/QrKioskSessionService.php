<?php

namespace App\Services;

use App\Models\KioskQrSession;
use Illuminate\Support\Str;

class QrKioskSessionService
{
    public function ttlSeconds(): int
    {
        $ttl = (int) env('KIOSK_QR_TTL_SECONDS', 60);
        return max(10, min(600, $ttl));
    }

    public function normalizeKioskId(string $kioskId): string
    {
        $id = trim($kioskId);
        if ($id === '') {
            return 'KIOSK_UNKNOWN';
        }
        // keep it URL-safe-ish
        $id = preg_replace('/[^A-Za-z0-9_-]+/', '_', $id) ?? $id;
        return substr($id, 0, 100);
    }

    public function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function createSession(string $kioskId, array $meta = [], ?int $createdBy = null): KioskQrSession
    {
        $kioskId = $this->normalizeKioskId($kioskId);

        // Invalidate any still-active session for this kiosk (keep history)
        KioskQrSession::where('kiosk_id', $kioskId)
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $ttl = $this->ttlSeconds();
        $expiresAt = now()->addSeconds($ttl);

        $codeTry = 0;
        do {
            $code = strtoupper(Str::random(12));
            $codeTry++;
        } while (KioskQrSession::where('code', $code)->exists() && $codeTry < 5);

        return KioskQrSession::create([
            'kiosk_id' => $kioskId,
            'code' => $code,
            'expires_at' => $expiresAt,
            'meta' => empty($meta) ? null : $meta,
            'created_by' => $createdBy,
        ]);
    }

    public function findActiveSessionByKioskId(string $kioskId): ?KioskQrSession
    {
        $kioskId = $this->normalizeKioskId($kioskId);

        return KioskQrSession::where('kiosk_id', $kioskId)
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{0: KioskQrSession, 1: bool} [session, created]
     */
    public function getOrCreateSession(string $kioskId, array $meta = [], ?int $createdBy = null): array
    {
        $existing = $this->findActiveSessionByKioskId($kioskId);
        if ($existing && ! $existing->isExpired()) {
            return [$existing, false];
        }

        return [$this->createSession($kioskId, $meta, $createdBy), true];
    }

    public function findActiveSessionByCode(string $code): ?KioskQrSession
    {
        $code = $this->normalizeCode($code);

        return KioskQrSession::where('code', $code)
            ->orderByDesc('id')
            ->first();
    }
}
