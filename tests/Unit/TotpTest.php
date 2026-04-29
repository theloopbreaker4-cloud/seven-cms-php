<?php

declare(strict_types=1);

namespace Seven\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TotpTest extends TestCase
{
    public function testGenerateSecretReturnsBase32String(): void
    {
        $secret = \Totp::generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        // 20 bytes -> 32 base32 chars
        $this->assertSame(32, strlen($secret));
    }

    public function testOtpAuthUriShape(): void
    {
        $uri = \Totp::otpAuthUri('JBSWY3DPEHPK3PXP', 'alice@example.com', 'Seven');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('issuer=Seven', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }

    public function testVerifyAcceptsCurrentCode(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP'; // RFC test vector friendly
        $code   = $this->currentCode($secret);
        $this->assertTrue(\Totp::verify($secret, $code));
    }

    public function testVerifyRejectsInvalidCode(): void
    {
        $this->assertFalse(\Totp::verify('JBSWY3DPEHPK3PXP', '000000'));
        $this->assertFalse(\Totp::verify('JBSWY3DPEHPK3PXP', '12345'));   // wrong length
        $this->assertFalse(\Totp::verify('!!!invalid!!!',  '123456'));    // bad secret
    }

    public function testRecoveryCodesUniqueAndConsumable(): void
    {
        $plain  = \Totp::generateRecoveryCodes(4);
        $this->assertCount(4, $plain);
        $this->assertCount(4, array_unique($plain));

        $hashes = \Totp::hashRecoveryCodes($plain);
        $this->assertCount(4, $hashes);

        $idx = \Totp::consumeRecoveryCode($hashes, $plain[2]);
        $this->assertSame(2, $idx);

        $miss = \Totp::consumeRecoveryCode($hashes, 'not-a-code');
        $this->assertNull($miss);
    }

    /** Generate the current TOTP code by reaching the private hotp helper. */
    private function currentCode(string $secret): string
    {
        $r = new ReflectionClass(\Totp::class);
        $hotp   = $r->getMethod('hotp');   $hotp->setAccessible(true);
        $decode = $r->getMethod('base32Decode'); $decode->setAccessible(true);

        $bin     = $decode->invoke(null, $secret);
        $counter = (int)floor(time() / 30);
        return $hotp->invoke(null, $bin, $counter);
    }
}
