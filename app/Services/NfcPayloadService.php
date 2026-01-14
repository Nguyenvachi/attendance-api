<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * NfcPayloadService
 * File mẹ: app/Services (service layer)
 * Mục tiêu: Chuẩn hoá "nội dung thẻ NFC" (NDEF payload) để app mobile có thể ghi lên thẻ.
 *
 * Payload dạng Text (NDEF):
 *   NCTNFC:v1:<user_id>:<token>
 *
 * Server lưu hash SHA-256 của token vào users.nfc_token_hash.
 */
class NfcPayloadService
{
    public const PREFIX = 'NCTNFC';

    /**
     * BỔ SUNG: TTL (ngày) cho NFC payload, mặc định 0 = không hết hạn.
     * Có thể set qua ENV: NFC_PAYLOAD_TTL_DAYS=90
     */
    public function getPayloadTtlDays(): int
    {
        return (int) env('NFC_PAYLOAD_TTL_DAYS', 0);
    }

    /**
     * Phát hành payload cho 1 user (tạo token mới và lưu hash).
     */
    public function issuePayloadForUser(User $user, int $version = 1): array
    {
        $token = $this->generateToken();
        $hash = $this->hashToken($token);

        $user->nfc_token_hash = $hash;
        $user->nfc_token_issued_at = now();
        $user->nfc_token_version = $version;
        $user->save();

        return [
            'version' => $version,
            'token' => $token,
            'payload' => $this->buildPayload($version, $user->id, $token),
        ];
    }

    /**
     * Resolve user từ chuỗi NFC (có thể là UID cũ hoặc payload mới).
     * - Nếu là payload hợp lệ -> trả về User
     * - Nếu không phải payload -> trả về null (để fallback sang lookup UID)
     */
    public function resolveUserFromNfcCode(string $nfcCode): ?User
    {
        // BỔ SUNG: normalize input để tránh lỗi do NDEF có ký tự ẩn/null
        $nfcCodeNormalized = $this->normalizeNfcCode($nfcCode);

        $parsed = $this->parsePayload($nfcCode);
        // BỔ SUNG: thử parse lại với input đã normalize
        if (! $parsed) {
            $parsed = $this->parsePayload($nfcCodeNormalized);
        }
        if (! $parsed) {
            return null;
        }

        $user = User::find($parsed['user_id']);
        if (! $user || ! $user->nfc_token_hash) {
            return null;
        }

        // BỔ SUNG: Nếu có TTL và token đã quá hạn thì từ chối
        $ttlDays = $this->getPayloadTtlDays();
        if ($ttlDays > 0 && $user->nfc_token_issued_at) {
            if ($user->nfc_token_issued_at->lt(now()->subDays($ttlDays))) {
                return null;
            }
        }

        $hash = $this->hashToken($parsed['token']);
        if (! hash_equals($user->nfc_token_hash, $hash)) {
            return null;
        }

        return $user;
    }

    public function buildPayload(int $version, int $userId, string $token): string
    {
        return self::PREFIX.':v'.$version.':'.$userId.':'.$token;
    }

    /**
     * @return array{version:int,user_id:int,token:string}|null
     */
    public function parsePayload(string $payload): ?array
    {
        // Dạng: NCTNFC:v1:<user_id>:<token>
        $parts = explode(':', trim($payload));

        // BỔ SUNG: normalize payload (loại null/control chars) rồi parse lại
        $payloadNormalized = $this->normalizeNfcCode($payload);
        $parts = explode(':', $payloadNormalized);
        if (count($parts) !== 4) {
            return null;
        }

        if (($parts[0] ?? '') !== self::PREFIX) {
            return null;
        }

        $versionRaw = $parts[1] ?? '';
        if (! Str::startsWith($versionRaw, 'v')) {
            return null;
        }

        $version = (int) substr($versionRaw, 1);
        $userId = (int) ($parts[2] ?? 0);
        $token = (string) ($parts[3] ?? '');

        if ($version < 1 || $userId < 1 || $token === '') {
            return null;
        }

        return [
            'version' => $version,
            'user_id' => $userId,
            'token' => $token,
        ];
    }

    /**
     * BỔ SUNG: Chuẩn hoá NFC code/payload từ NDEF.
     * - trim
     * - bỏ ký tự null (\0)
     * - bỏ control chars (\x00-\x1F, \x7F)
     */
    private function normalizeNfcCode(string $raw): string
    {
        $value = trim($raw);
        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;

        return trim($value);
    }

    private function generateToken(): string
    {
        // URL-safe, đủ dài để khó đoán.
        return Str::random(48);
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
