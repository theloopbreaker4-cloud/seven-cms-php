<?php

declare(strict_types=1);

namespace Seven\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class JwtTest extends TestCase
{
    protected function setUp(): void
    {
        // Stable secret for tests.
        $_ENV['JWT_SECRET']    = 'unit-test-secret';
        $_SERVER['JWT_SECRET'] = 'unit-test-secret';
    }

    public function testSignAndVerify(): void
    {
        $token = \Jwt::sign(['sub' => 42, 'role' => 'admin'], 30);
        $this->assertIsString($token);
        $this->assertSame(3, substr_count($token, '.') + 1);

        $claims = \Jwt::verify($token);
        $this->assertIsArray($claims);
        $this->assertSame(42, $claims['sub']);
        $this->assertSame('admin', $claims['role']);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertArrayHasKey('jti', $claims);
    }

    public function testTamperedSignatureFailsVerification(): void
    {
        $token = \Jwt::sign(['sub' => 1], 30);
        $tampered = substr($token, 0, -2) . 'XX';
        $this->assertNull(\Jwt::verify($tampered));
    }

    public function testExpiredTokenIsRejected(): void
    {
        // ttl is clamped to ≥ 1 second by sign(), so manually craft expired claims.
        $h = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $p = json_encode(['exp' => time() - 10, 'sub' => 1]);
        $hb = rtrim(strtr(base64_encode((string)$h), '+/', '-_'), '=');
        $pb = rtrim(strtr(base64_encode((string)$p), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(
            hash_hmac('sha256', "{$hb}.{$pb}", 'unit-test-secret', true)
        ), '+/', '-_'), '=');

        $this->assertNull(\Jwt::verify("{$hb}.{$pb}.{$sig}"));
    }

    public function testMalformedTokenReturnsNull(): void
    {
        $this->assertNull(\Jwt::verify('not.a.token.really'));
        $this->assertNull(\Jwt::verify('only-one-segment'));
    }
}
