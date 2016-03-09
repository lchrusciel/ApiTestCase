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
interface ResponseContentMatcherInterface
{
    /**
     * @param string $actualResponse
     * @param string $expectedResponse
     */
    public function matchResponse($actualResponse, $expectedResponse);

    /**
     * @return bool
     */
    public function hasError();

    /**
     * @return null|string
     */
    public function getError();

    /**
     * @return null|string
     */
    public function getDifference();
}
