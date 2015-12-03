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

use Lakion\ApiTestCase\MediaTypes;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Łukasz Chruściel <lukasz.chrusciel@lakion.com>
 */
class SampleController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function basicAction(Request $request)
    {
        $accept = $request->headers->get('Accept');

        if ('application/json' === $accept) {
            return new JsonResponse(['message' => 'Hello ApiTestCase World!']);
        }

        $content = '<?xml version="1.0" encoding="UTF-8"?><greetings>Hello world!</greetings>';

        $response = new Response($content);
        $response->headers->set('Content-Type', MediaTypes::XML);

        return $response;
    }

    /**
     * @return JsonResponse
     */
    public function notSoBasicAction(Request $request)
    {
        $accept = $request->headers->get('Accept');

        if ('application/json' === $accept) {
            return new JsonResponse($this->get('app.service')->getOutsideApiResponse());
        }

        $content = $this->get('app.service')->getOutsideApiResponse();
        $content = sprintf('<?xml version="1.0" encoding="UTF-8"?><message>%s</message>', $content['message']);

        $response = new Response($content);
        $response->headers->set('Content-Type', MediaTypes::XML);

        return $response;
    }
}
