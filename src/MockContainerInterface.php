<?php

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Łukasz Chruściel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lakion\ApiTestCase;

use Mockery\MockInterface;

interface MockContainerInterface
{
    public function getMockedServices(): array;

    public function unmock($id): void;

    public function mock(): MockInterface;
}
