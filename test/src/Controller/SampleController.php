<?php

declare(strict_types=1);

/*
 * This file is part of the ApiTestCase package.
 *
 * (c) Łukasz Chruściel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ApiTestCase\Test\Controller;

use ApiTestCase\Test\Entity\Product;
use ApiTestCase\Test\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class SampleController
{
    /** @var EntityManagerInterface */
    private $objectManager;

    public function __construct(EntityManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function helloWorldAction(Request $request): Response
    {
        $acceptFormat = $request->headers->get('Accept');

        if (
            false !== strpos($acceptFormat, 'application')
            && false !== strpos($acceptFormat, 'json')
        ) {
            return new JsonResponse([
                'message' => 'Hello ApiTestCase World!',
                'unicode' => '€ ¥ 💰',
                'path' => '/p/a/t/h',
            ], 200, [
                'Content-Type' => $acceptFormat,
            ]);
        }

        $content = '<?xml version="1.0" encoding="UTF-8"?><greetings>Hello world!</greetings>';

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }

    public function productIndexAction(Request $request): Response
    {
        $productRepository = $this->objectManager->getRepository(Product::class);
        $products = $productRepository->findAll();

        return $this->respond($request, $products);
    }

    public function categoryIndexAction(Request $request): Response
    {
        $categoryRepository = $this->objectManager->getRepository(Category::class);
        $categories = $categoryRepository->findAll();

        return $this->respond($request, $categories);
    }

    public function showAction(Request $request): Response
    {
        $productRepository = $this->objectManager->getRepository(Product::class);
        $product = $productRepository->find($request->attributes->get('id'));

        if (!$product) {
            throw new NotFoundHttpException();
        }

        return $this->respond($request, $product);
    }

    public function createAction(Request $request): Response
    {
        $product = new Product();
        $product->setName($request->request->get('name'));
        $product->setPrice($request->request->getInt('price'));
        $product->setUuid($request->request->get('uuid'));

        $this->objectManager->persist($product);
        $this->objectManager->flush();

        return $this->respond($request, $product, Response::HTTP_CREATED);
    }

    private function respond(Request $request, $data, int $statusCode = Response::HTTP_OK): Response
    {
        $serializer = $this->createSerializer();
        $acceptFormat = $request->headers->get('Accept');

        if ('application/xml' === $acceptFormat) {
            $content = $serializer->serialize($data, 'xml');

            $response = new Response($content, $statusCode);
            $response->headers->set('Content-Type', 'application/xml');

            return $response;
        }

        if ('application/json' === $acceptFormat) {
            $content = $serializer->serialize($data, 'json');
            $response = new Response($content, $statusCode);
            $response->headers->set('Content-Type', 'application/json');

            return $response;
        }
    }

    private function createSerializer(): Serializer
    {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];

        return new Serializer($normalizers, $encoders);
    }
}
