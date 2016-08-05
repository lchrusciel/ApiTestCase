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

use Doctrine\Common\Persistence\ObjectManager;
use Lakion\ApiTestCase\MediaTypes;
use Lakion\ApiTestCase\Test\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

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
    public function helloWorldAction(Request $request)
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
    public function useThirdPartyApiAction(Request $request)
    {
        $acceptFormat = $request->headers->get('Accept');
        $content = $this->get('app.third_party_api_client')->getInventory();

        if ('application/json' === $acceptFormat) {
            return new JsonResponse($content);
        }

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
    public function productIndexAction(Request $request)
    {
        $productRepository = $this->getDoctrine()->getRepository('ApiTestCase:Product');
        $products = $productRepository->findAll();

        return $this->respond($request, $products);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse|Response
     */
    public function showAction(Request $request)
    {
        $productRepository = $this->getDoctrine()->getRepository('ApiTestCase:Product');
        $product = $productRepository->find($request->get('id'));

        if (!$product) {
            throw $this->createNotFoundException();
        }

        return $this->respond($request, $product);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function createAction(Request $request)
    {
        $product = new Product();
        $product->setName($request->request->get('name'));
        $product->setPrice($request->request->get('price'));

        /** @var ObjectManager $productManager */
        $productManager = $this->getDoctrine()->getManager();
        $productManager->persist($product);
        $productManager->flush();

        return $this->respond($request, $product, Response::HTTP_CREATED);
    }

    /**
     * @param Request $request
     * @param mixed $data
     * @param int $statusCode
     *
     * @return Response
     */
    private function respond(Request $request, $data, $statusCode = Response::HTTP_OK)
    {
        $serializer = $this->createSerializer();
        $acceptFormat = $request->headers->get('Accept');

        if ('application/xml' === $acceptFormat) {
            $content = $serializer->serialize($data, 'xml');

            $response = new Response($content, $statusCode);
            $response->headers->set('Content-Type', MediaTypes::XML);

            return $response;
        }

        if ('application/json' === $acceptFormat) {
            $content = $serializer->serialize($data, 'json');
            $response = new Response($content, $statusCode);
            $response->headers->set('Content-Type', MediaTypes::JSON);

            return $response;
        }
    }

    /**
     * @return Serializer
     */
    private function createSerializer()
    {
        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new ObjectNormalizer());
        $serializer = new Serializer($normalizers, $encoders);

        return $serializer;
    }
}
