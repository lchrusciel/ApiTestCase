<?php

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
    /**
     * @return Matcher
     */
    public function buildXmlMatcher()
    {
        return $this->buildMatcher(Matcher\XmlMatcher::class);
    }

    /**
     * @return Matcher
     */
    public function buildJsonMatcher()
    {
        return $this->buildMatcher(Matcher\JsonMatcher::class);
    }

    /**
     * @param string $matcherClass
     *
     * @return Matcher
     */
    protected function buildMatcher($matcherClass)
    {
        $orMatcher = $this->buildOrMatcher();
        $chainMatcher = new Matcher\ChainMatcher(array(
            new $matcherClass($orMatcher),
        ));

        return new Matcher($chainMatcher);
    }
}
