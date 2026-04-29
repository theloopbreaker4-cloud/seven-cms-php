<?php

declare(strict_types=1);

namespace Seven\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContainerTest extends TestCase
{
    protected function setUp(): void
    {
        \Container::reset();
    }

    public function testSetAndGet(): void
    {
        $obj = new \stdClass();
        \Container::set('thing', $obj);
        $this->assertSame($obj, \Container::get('thing'));
    }

    public function testHasReturnsTrueForRegistered(): void
    {
        \Container::set('a', 1);
        $this->assertTrue(\Container::has('a'));
        $this->assertFalse(\Container::has('b'));
    }

    public function testFactoryIsCachedAfterFirstCall(): void
    {
        $count = 0;
        \Container::factory('counter', function () use (&$count) {
            $count++;
            return new \stdClass();
        });
        $a = \Container::get('counter');
        $b = \Container::get('counter');
        $this->assertSame($a, $b);
        $this->assertSame(1, $count, 'factory should run once');
    }

    public function testBindRebuildsEachCall(): void
    {
        \Container::bind('fresh', fn() => new \stdClass());
        $this->assertNotSame(\Container::get('fresh'), \Container::get('fresh'));
    }

    public function testGetMissingThrows(): void
    {
        $this->expectException(RuntimeException::class);
        \Container::get('nope');
    }

    public function testFactoryCanResolveOtherServices(): void
    {
        \Container::set('answer', 42);
        \Container::factory('echo', fn($c) => 'is ' . $c->get('answer'));
        $this->assertSame('is 42', \Container::get('echo'));
    }
}
