<?php

namespace atk4\ui\tests;

use atk4\core\AtkPhpunit;

class BasicTest extends AtkPhpunit\TestCase
{
    /**
     * Test constructor.
     */
    public function testTesting()
    {
        $this->assertSame('foo', 'foo');
    }
}
