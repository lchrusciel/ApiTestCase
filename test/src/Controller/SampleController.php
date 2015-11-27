<?php

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Lakion
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lakion\ApiTestCase\Test\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 */
class SampleController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function basicAction()
    {
        return new JsonResponse(['message' => 'Hello ApiTestCase World!']);
    }

    /**
     * @return JsonResponse
     */
    public function notSoBasicAction()
    {
        return new JsonResponse($this->get('app.service')->getOutsideApiResponse());
    }
}
