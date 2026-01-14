<?php

namespace Tests\Unit;

use App\Services\NfcPayloadService;
use PHPUnit\Framework\TestCase;

class NfcPayloadServiceTest extends TestCase
{
    public function test_build_payload_format_is_stable(): void
    {
        $service = new NfcPayloadService();
        $payload = $service->buildPayload(1, 12, 'TOKEN123');

        $this->assertSame('NCTNFC:v1:12:TOKEN123', $payload);
    }

    public function test_parse_payload_accepts_whitespace_and_null_bytes(): void
    {
        $service = new NfcPayloadService();

        $raw = "  NCTNFC:v1:99:ABCDEF\0\0\n";
        $parsed = $service->parsePayload($raw);

        $this->assertIsArray($parsed);
        $this->assertSame(1, $parsed['version']);
        $this->assertSame(99, $parsed['user_id']);
        $this->assertSame('ABCDEF', $parsed['token']);
    }

    public function test_parse_payload_rejects_invalid_format(): void
    {
        $service = new NfcPayloadService();

        $this->assertNull($service->parsePayload('NCTNFC:v1:1'));
        $this->assertNull($service->parsePayload('WRONG:v1:1:ABC'));
        $this->assertNull($service->parsePayload('NCTNFC:v0:1:ABC'));
        $this->assertNull($service->parsePayload('NCTNFC:v1:0:ABC'));
        $this->assertNull($service->parsePayload('NCTNFC:v1:1:'));
    }
}
