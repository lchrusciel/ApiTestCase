<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lakion\ApiTestCase\Matcher;

use Coduo\PHPMatcher\Factory\SimpleFactory;
use Coduo\PHPMatcher\Matcher;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
class DefaultMatcher implements MatcherInterface
{
    /**
     * @var Matcher
     */
    private $matcher;

    public function __construct()
    {
        $factory = new SimpleFactory();
        $this->matcher = $factory->createMatcher();
    }

    /**
     * {@inheritdoc}
     */
    public function match($value, $pattern)
    {
        return $this->matcher->match($value, $pattern);
    }

    /**
     * {@inheritdoc}
     */
    public function getError()
    {
        return $this->matcher->getError();
    }
}
