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
use Coduo\PHPMatcher\Lexer;
use Coduo\PHPMatcher\Matcher;

class MatcherFactory extends Factory\SimpleFactory
{
    public function buildXmlMatcher(): Matcher
    {
        return $this->buildMatcher(Matcher\XmlMatcher::class);
    }

    public function buildJsonMatcher(): Matcher
    {
        return $this->buildMatcher(Matcher\JsonMatcher::class);
    }

    protected function buildMatcher(string $matcherClass): Matcher
    {
        $orMatcher = $this->buildOrMatcher();
        $chainMatcher = new Matcher\ChainMatcher(array(
            new $matcherClass($orMatcher),
        ));

        return new Matcher($chainMatcher);
    }
}
