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
class PathBuilder
{
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
