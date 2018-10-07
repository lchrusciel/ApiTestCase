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

namespace ApiTestCase;

final class PathBuilder
{
    /**
     * Hacky way to create a 'static' php class
     */
    private function __construct()
    {
    }

    public static function build(string ...$segments): string
    {
        return implode(\DIRECTORY_SEPARATOR, $segments);
    }
}
