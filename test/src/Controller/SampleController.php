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
     * @param Request $request
     *
     * @return JsonResponse|Response
     */
    public function basicAction(Request $request)
    {
        $acceptFormat = $request->headers->get('Accept');

        if ('application/json' === $acceptFormat) {
            return new JsonResponse(['message' => 'Hello ApiTestCase World!']);
        }

        $content = '<?xml version="1.0" encoding="UTF-8"?><greetings>Hello world!</greetings>';

        $response = new Response($content);
        $response->headers->set('Content-Type', MediaTypes::XML);

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse|Response
     */
    public function notSoBasicAction(Request $request)
    {
        $acceptFormat = $request->headers->get('Accept');

        if ('application/json' === $acceptFormat) {
            return new JsonResponse($this->get('app.service')->getOutsideApiResponse());
        }

        $content = $this->get('app.service')->getOutsideApiResponse();
        $content = sprintf('<?xml version="1.0" encoding="UTF-8"?><message>%s</message>', $content['message']);

        $response = new Response($content);
        $response->headers->set('Content-Type', MediaTypes::XML);

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse|Response
     */
    public function indexAction(Request $request)
    {
        $acceptFormat = $request->headers->get('Accept');
        if ('application/xml' === $acceptFormat) {
            $content = sprintf('
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <id>%s</id>
        <name>"Star-Wars T-shirt"</name>
        <sku>"SWTS"</sku>
        <price>5500</price>
    </product>
    <product>
        <id>%s</id>
        <name>"Han Solo Mug"</name>
        <sku>"HSM"</sku>
        <price>500</price>
    </product>
</products>'
                , rand(), rand());

            $response = new Response($content);
            $response->headers->set('Content-Type', MediaTypes::XML);

            return $response;
        }

        if ('application/json' === $acceptFormat) {
            $content = array(
                array(
                    'id' => rand(),
                    'name' => 'Star-Wars T-shirt',
                    'sku' => 'SWTS',
                    'price' => 5500,
                ),
                array(
                    'id' => rand(),
                    'name' => 'Han Solo Mug',
                    'sku' => 'HSM',
                    'price' => 500,
                ),
            );

            return new JsonResponse($content);
        }
    }
}
