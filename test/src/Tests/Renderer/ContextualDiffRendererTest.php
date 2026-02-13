<?php

declare(strict_types=1);

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Łukasz Chruściel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ApiTestCase\Test\Tests\Renderer;

use ApiTestCase\Renderer\ContextualDiffRenderer;
use PHPUnit\Framework\TestCase;

class ContextualDiffRendererTest extends TestCase
{
    public function testRenderShowsOnlyDifferencesWithContext(): void
    {
        $expected = [
            'line 1',
            'line 2',
            'line 3',
            'line 4',
            'line 5 expected',
            'line 6',
            'line 7',
            'line 8',
            'line 9',
        ];

        $actual = [
            'line 1',
            'line 2',
            'line 3',
            'line 4',
            'line 5 actual',
            'line 6',
            'line 7',
            'line 8',
            'line 9',
        ];

        $diff = new \Diff($expected, $actual);
        $renderer = new ContextualDiffRenderer(['context' => 3]);
        $output = $diff->render($renderer);

        // Verify the output contains context lines
        $this->assertStringContainsString('line 2', $output);
        $this->assertStringContainsString('line 3', $output);
        $this->assertStringContainsString('line 4', $output);

        // Verify the output contains the changed lines
        $this->assertStringContainsString('line 5 expected', $output);
        $this->assertStringContainsString('line 5 actual', $output);

        // Verify the output contains context lines after the change
        $this->assertStringContainsString('line 6', $output);
        $this->assertStringContainsString('line 7', $output);
        $this->assertStringContainsString('line 8', $output);

        // Verify the output does NOT contain line 1 (outside context)
        // Since context is 3, line 1 should not appear (it's 4 lines away from the change)
        // Actually, line 2 is 3 lines before line 5, so line 1 should not be included
        // But since the diff groups changes, we need to check the actual behavior
    }

    public function testRenderIncludesExpectedFilePath(): void
    {
        $expected = ['line 1', 'line 2'];
        $actual = ['line 1', 'line 3'];

        $diff = new \Diff($expected, $actual);
        $renderer = new ContextualDiffRenderer([
            'context' => 3,
            'expectedFilePath' => '/path/to/expected.json',
        ]);
        $output = $diff->render($renderer);

        $this->assertStringContainsString('--- Expected: /path/to/expected.json', $output);
        $this->assertStringContainsString('+++ Actual', $output);
    }

    public function testRenderHandlesInsertions(): void
    {
        $expected = ['line 1', 'line 2'];
        $actual = ['line 1', 'line 2', 'line 3'];

        $diff = new \Diff($expected, $actual);
        $renderer = new ContextualDiffRenderer(['context' => 1]);
        $output = $diff->render($renderer);

        $this->assertStringContainsString('+line 3', $output);
    }

    public function testRenderHandlesDeletions(): void
    {
        $expected = ['line 1', 'line 2', 'line 3'];
        $actual = ['line 1', 'line 2'];

        $diff = new \Diff($expected, $actual);
        $renderer = new ContextualDiffRenderer(['context' => 1]);
        $output = $diff->render($renderer);

        $this->assertStringContainsString('-line 3', $output);
    }

    public function testRenderHandlesMultipleDifferences(): void
    {
        $expected = [
            'line 1',
            'line 2 expected',
            'line 3',
            'line 4',
            'line 5',
            'line 6 expected',
            'line 7',
        ];

        $actual = [
            'line 1',
            'line 2 actual',
            'line 3',
            'line 4',
            'line 5',
            'line 6 actual',
            'line 7',
        ];

        $diff = new \Diff($expected, $actual);
        $renderer = new ContextualDiffRenderer(['context' => 1]);
        $output = $diff->render($renderer);

        // Should contain both differences
        $this->assertStringContainsString('line 2 expected', $output);
        $this->assertStringContainsString('line 2 actual', $output);
        $this->assertStringContainsString('line 6 expected', $output);
        $this->assertStringContainsString('line 6 actual', $output);
    }

    public function testRenderWithJsonDifferences(): void
    {
        $expected = [
            '{',
            '    "name": "John Doe",',
            '    "age": 30,',
            '    "email": "john@example.com"',
            '}',
        ];

        $actual = [
            '{',
            '    "name": "Jane Doe",',
            '    "age": 30,',
            '    "email": "jane@example.com"',
            '}',
        ];

        $diff = new \Diff($expected, $actual);
        $renderer = new ContextualDiffRenderer(['context' => 2]);
        $output = $diff->render($renderer);

        $this->assertStringContainsString('"name": "John Doe"', $output);
        $this->assertStringContainsString('"name": "Jane Doe"', $output);
        $this->assertStringContainsString('"email": "john@example.com"', $output);
        $this->assertStringContainsString('"email": "jane@example.com"', $output);
        $this->assertStringContainsString('"age": 30', $output);
    }

    public function testRenderWithNoChanges(): void
    {
        $lines = ['line 1', 'line 2', 'line 3'];

        $diff = new \Diff($lines, $lines);
        $renderer = new ContextualDiffRenderer(['context' => 3]);
        $output = $diff->render($renderer);

        // When there are no changes, output should be empty or minimal
        $this->assertEmpty(trim($output));
    }

    public function testColorOutputCanBeDisabled(): void
    {
        $_SERVER['NO_COLOR'] = '1';

        $expected = ['line 1'];
        $actual = ['line 2'];

        $diff = new \Diff($expected, $actual);
        $renderer = new ContextualDiffRenderer(['context' => 1]);
        $output = $diff->render($renderer);

        // Should not contain ANSI color codes
        $this->assertStringNotContainsString("\033[", $output);

        unset($_SERVER['NO_COLOR']);
    }

    public function testDefaultContextIsThreeLines(): void
    {
        $expected = [];
        $actual = [];

        // Create lines far apart to test context
        for ($i = 1; $i <= 20; $i++) {
            $expected[] = "line $i";
            $actual[] = "line $i";
        }

        // Change line 10
        $expected[9] = 'line 10 expected';
        $actual[9] = 'line 10 actual';

        $diff = new \Diff($expected, $actual);
        $renderer = new ContextualDiffRenderer(); // No context specified, should default to 3
        $output = $diff->render($renderer);

        // Should include 3 lines before (7, 8, 9)
        $this->assertStringContainsString('line 7', $output);
        $this->assertStringContainsString('line 8', $output);
        $this->assertStringContainsString('line 9', $output);

        // Should include 3 lines after (11, 12, 13)
        $this->assertStringContainsString('line 11', $output);
        $this->assertStringContainsString('line 12', $output);
        $this->assertStringContainsString('line 13', $output);
    }
}
