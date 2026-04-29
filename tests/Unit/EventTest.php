<?php

declare(strict_types=1);

namespace Seven\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class EventTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $f = ROOT_DIR . '/lib/event.class.php';
        if (!class_exists('Event') && is_file($f)) require_once $f;
    }

    protected function setUp(): void
    {
        // Reset listeners between tests.
        $r = new ReflectionClass(\Event::class);
        $p = $r->getProperty('listeners');
        $p->setAccessible(true);
        $p->setValue(null, []);
    }

    public function testOnAndEmit(): void
    {
        $log = [];
        \Event::on('user.login', function ($data) use (&$log) { $log[] = $data; });
        \Event::emit('user.login', ['id' => 7]);
        $this->assertSame([['id' => 7]], $log);
    }

    public function testDispatchAcceptsAnyPayload(): void
    {
        $captured = null;
        \Event::listen('thing.changed', function ($x) use (&$captured) { $captured = $x; });

        $obj = new \stdClass();
        $obj->name = 'x';
        \Event::dispatch('thing.changed', $obj);
        $this->assertSame($obj, $captured);
    }

    public function testListenAndOnShareSameRegistry(): void
    {
        $a = 0; $b = 0;
        \Event::on('e',     function () use (&$a) { $a++; });
        \Event::listen('e', function () use (&$b) { $b++; });
        \Event::emit('e', []);
        $this->assertSame(1, $a);
        $this->assertSame(1, $b);
    }

    public function testOff(): void
    {
        $count = 0;
        \Event::on('x', function () use (&$count) { $count++; });
        \Event::off('x');
        \Event::emit('x', []);
        $this->assertSame(0, $count);
        $this->assertFalse(\Event::hasListeners('x'));
    }
}
