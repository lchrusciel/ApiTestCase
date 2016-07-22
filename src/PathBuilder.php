<?php

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Lakion
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lakion\ApiTestCase;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 */
final class PathBuilder
{
    /**
     * Hacky way to create a 'static' php class
     */
    private function __construct()
    {
    }

    /**
     * @param array ...$segments unlimited number of path segments
     *
     * @return string
     */
    public static function build(...$segments)
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
