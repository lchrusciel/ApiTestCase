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

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
interface MatcherInterface
{
    /**
     * @param $value
     * @param $pattern
     *
     * @return mixed
     */
    public function match($value, $pattern);

    /**
     * @return null|string
     */
    public function getError();
}
