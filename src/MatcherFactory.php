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

use Coduo\PHPMatcher\Factory;
use Coduo\PHPMatcher\Lexer;
use Coduo\PHPMatcher\Matcher;
use Coduo\PHPMatcher\Parser;

class MatcherFactory
{
    /**
     * @return Matcher
     */
    public static function buildXmlMatcher()
    {
        return self::buildMatcher(Matcher\XmlMatcher::class);
    }

    /**
     * @return Matcher
     */
    public static function buildJsonMatcher()
    {
        return self::buildMatcher(Matcher\JsonMatcher::class);
    }

    /**
     * @param string $matcherClass
     *
     * @return Matcher
     */
    protected static function buildMatcher($matcherClass)
    {
        $orMatcher = self::buildOrMatcher();
        $chainMatcher = new Matcher\ChainMatcher(array(
            new $matcherClass($orMatcher),
        ));

        return new Matcher($chainMatcher);
    }

    /**
     * @return Matcher\ChainMatcher
     */
    protected static function buildOrMatcher()
    {
        $scalarMatchers = self::buildScalarMatchers();
        $orMatcher = new Matcher\OrMatcher($scalarMatchers);
        $arrayMatcher = new Matcher\ArrayMatcher(
            new Matcher\ChainMatcher(array(
                $orMatcher,
                $scalarMatchers
            )),
            self::buildParser()
        );

        $chainMatcher = new Matcher\ChainMatcher(array(
            $orMatcher,
            $arrayMatcher,
        ));

        return $chainMatcher;
    }

    /**
     * @return Matcher\ChainMatcher
     */
    protected static function buildScalarMatchers()
    {
        $parser = self::buildParser();

        return new Matcher\ChainMatcher(array(
            new Matcher\CallbackMatcher(),
            new Matcher\ExpressionMatcher(),
            new Matcher\NullMatcher(),
            new Matcher\StringMatcher($parser),
            new Matcher\IntegerMatcher($parser),
            new Matcher\BooleanMatcher($parser),
            new Matcher\DoubleMatcher($parser),
            new Matcher\NumberMatcher($parser),
            new Matcher\ScalarMatcher(),
            new Matcher\WildcardMatcher()
        ));
    }

    /**
     * @return Parser
     */
    protected static function buildParser()
    {
        return new Parser(new Lexer(), new Parser\ExpanderInitializer());
    }
}
