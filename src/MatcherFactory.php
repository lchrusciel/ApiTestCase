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

use Coduo\PHPMatcher\Factory;
use Coduo\PHPMatcher\Matcher;

class MatcherFactory extends Factory\SimpleFactory
{
    public function buildXmlMatcher(): Matcher
    {
        $matcherFactory = new Factory\MatcherFactory();

        return $matcherFactory->createMatcher();
    }

    public function buildJsonMatcher(): Matcher
    {
        $matcherFactory = new Factory\MatcherFactory();

        return $matcherFactory->createMatcher();
    }
}
