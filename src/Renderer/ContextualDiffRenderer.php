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

namespace ApiTestCase\Renderer;

use Coduo\PHPMatcher\Matcher;

/**
 * PHPUnit-like contextual diff renderer.
 * Shows only differences with configurable context lines above and below each change.
 * Marks missing expected values in red and unexpected actual values in green.
 */
class ContextualDiffRenderer extends \Diff_Renderer_Abstract
{
    /**
     * @var \Diff
     */
    public $diff;

    /**
     * @var string|null Path to the expected response file for display
     */
    private ?string $expectedFilePath;

    /**
     * @var Matcher|null PHPMatcher instance for pattern-aware diffing
     */
    private ?Matcher $matcher;

    /**
     * @param array<string, mixed> $options Optional configuration:
     *                                      - 'expectedFilePath' (string): Path to expected response file
     *                                      - 'matcher' (Matcher): PHPMatcher for pattern-aware diffing
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->expectedFilePath = $options['expectedFilePath'] ?? null;
        $this->matcher = $options['matcher'] ?? null;
    }

    /**
     * Render and return a contextual diff with color highlighting.
     *
     * @return string The contextual diff output
     */
    public function render(): string
    {
        $output = '';

        $opCodes = $this->diff->getGroupedOpcodes();

        foreach ($opCodes as $group) {
            $lastItem = count($group) - 1;
            $i1 = $group[0][1];
            $i2 = $group[$lastItem][2];
            $j1 = $group[0][3];
            $j2 = $group[$lastItem][4];

            if ($i1 == 0 && $i2 == 0) {
                $i1 = -1;
                $i2 = -1;
            }

            $output .= sprintf("@@ -%d,%d +%d,%d @@\n", $i1 + 1, $i2 - $i1, $j1 + 1, $j2 - $j1);

            foreach ($group as $code) {
                [$tag, $i1, $i2, $j1, $j2] = $code;

                if ($tag === 'equal') {
                    // Context lines (unchanged)
                    $lines = $this->diff->GetA($i1, $i2);
                    foreach ($lines as $line) {
                        $output .= ' ' . $line . "\n";
                    }
                } elseif ($tag === 'replace' && $this->matcher !== null) {
                    $expectedLines = $this->diff->GetA($i1, $i2);
                    $actualLines = $this->diff->GetB($j1, $j2);
                    $maxLines = max(count($expectedLines), count($actualLines));

                    for ($lineIndex = 0; $lineIndex < $maxLines; $lineIndex++) {
                        $expectedLine = $expectedLines[$lineIndex] ?? '';
                        $actualLine = $actualLines[$lineIndex] ?? '';

                        if ($this->linesMatchViaPattern($actualLine, $expectedLine)) {
                            $output .= ' ' . $actualLine . "\n";
                        } else {
                            if (isset($expectedLines[$lineIndex])) {
                                $output .= $this->colorRed('-' . $expectedLine) . "\n";
                            }
                            if (isset($actualLines[$lineIndex])) {
                                $output .= $this->colorGreen('+' . $actualLine) . "\n";
                            }
                        }
                    }
                } else {
                    // Handle deletions (expected but not found in actual)
                    if ($tag === 'replace' || $tag === 'delete') {
                        $lines = $this->diff->GetA($i1, $i2);
                        foreach ($lines as $line) {
                            $output .= $this->colorRed('-' . $line) . "\n";
                        }
                    }

                    // Handle insertions (unexpected in actual)
                    if ($tag === 'replace' || $tag === 'insert') {
                        $lines = $this->diff->GetB($j1, $j2);
                        foreach ($lines as $line) {
                            $output .= $this->colorGreen('+' . $line) . "\n";
                        }
                    }
                }
            }
        }

        // Add expected file path at the end if provided
        if ($this->expectedFilePath !== null) {
            $absolutePath = realpath($this->expectedFilePath) ?: $this->expectedFilePath;
            $output .= sprintf("\nExpected file: %s\n", $absolutePath);
        }

        return $output;
    }

    /**
     * Check if actual line matches expected line via phpmatcher patterns.
     *
     * @param string $actualLine The actual line from response
     * @param string $expectedLine The expected line (may contain patterns)
     * @return bool True if lines match via patterns
     */
    private function linesMatchViaPattern(string $actualLine, string $expectedLine): bool
    {
        if ($this->matcher === null) {
            return false;
        }

        $result = $this->matcher->match($actualLine, $expectedLine);

        return $result;
    }

    /**
     * Apply red ANSI color code to a string (for expected but missing values).
     *
     * @param string $text Text to colorize
     * @return string Colorized text
     */
    private function colorRed(string $text): string
    {
        if ($this->isColorSupported()) {
            return "\033[31m" . $text . "\033[0m";
        }

        return $text;
    }

    /**
     * Apply green ANSI color code to a string (for unexpected actual values).
     *
     * @param string $text Text to colorize
     * @return string Colorized text
     */
    private function colorGreen(string $text): string
    {
        if ($this->isColorSupported()) {
            return "\033[32m" . $text . "\033[0m";
        }

        return $text;
    }

    /**
     * Check if ANSI color codes are supported in the current environment.
     *
     * @return bool True if colors are supported
     */
    private function isColorSupported(): bool
    {
        // Check if explicitly disabled
        if (isset($_SERVER['NO_COLOR']) || getenv('NO_COLOR') !== false) {
            return false;
        }

        // Check if output is to a terminal
        if (function_exists('posix_isatty') && defined('STDOUT')) {
            return @posix_isatty(STDOUT);
        }

        // For Windows, check for ANSICON or ConEmu or Windows 10+
        if (DIRECTORY_SEPARATOR === '\\') {
            return (getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON' ||
                    (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT)));
        }

        // Default to true for Unix-like systems
        return true;
    }
}
