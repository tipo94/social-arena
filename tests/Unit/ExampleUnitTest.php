<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleUnitTest extends TestCase
{
    /**
     * Test basic arithmetic operations
     */
    public function test_basic_arithmetic(): void
    {
        $this->assertEquals(4, 2 + 2);
        $this->assertEquals(6, 2 * 3);
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    /**
     * Test string operations
     */
    public function test_string_operations(): void
    {
        $text = 'AI-Book Social Network';
        
        $this->assertStringContainsString('AI-Book', $text);
        $this->assertStringStartsWith('AI-Book', $text);
        $this->assertStringEndsWith('Network', $text);
        $this->assertEquals(22, strlen($text));
    }

    /**
     * Test array operations
     */
    public function test_array_operations(): void
    {
        $array = ['apple', 'banana', 'cherry'];
        
        $this->assertCount(3, $array);
        $this->assertContains('banana', $array);
        $this->assertNotContains('orange', $array);
        $this->assertEquals('apple', $array[0]);
    }

    /**
     * Test exception handling
     */
    public function test_exception_handling(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid argument provided');
        
        throw new \InvalidArgumentException('Invalid argument provided');
    }
} 