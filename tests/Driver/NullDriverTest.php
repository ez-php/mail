<?php

declare(strict_types=1);

namespace Tests\Driver;

use EzPhp\Mail\Driver\NullDriver;
use EzPhp\Mail\Mailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class NullDriverTest
 *
 * @package Tests\Driver
 */
#[CoversClass(NullDriver::class)]
#[UsesClass(Mailable::class)]
final class NullDriverTest extends TestCase
{
    public function testSendDoesNotThrow(): void
    {
        $driver = new NullDriver();

        // Should complete without throwing — addToAssertionCount prevents "no assertions" risky warning
        $driver->send((new Mailable())->to('a@b.com')->subject('Hi')->text('body'));

        $this->addToAssertionCount(1);
    }

    public function testSendDoesNotProduceOutput(): void
    {
        $driver = new NullDriver();

        ob_start();
        $driver->send((new Mailable())->to('a@b.com')->subject('Hi')->text('body'));
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }
}
