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
class ResponseContentMatcher implements ResponseContentMatcherInterface
{
    /**
     * @var MatcherInterface
     */
    private $matcher;

    /**
     * @var string
     */
    private $error;

    /**
     * @var string
     */
    private $difference;

    /**
     * @param MatcherInterface $matcher
     */
    public function __construct(MatcherInterface $matcher)
    {
        $this->matcher = $matcher;
    }

    /**
     * {@inheritdoc}
     */
    public function matchResponse($actualResponse, $expectedResponse)
    {
        if (!$this->matcher->match($actualResponse, $expectedResponse)) {
            $this->error = $this->matcher->getError();

            $difference = $this->error;
            $difference = $difference.PHP_EOL;

            $expectedResponse = explode(PHP_EOL, (string)$expectedResponse);
            $actualResponse = explode(PHP_EOL, (string)$actualResponse);

            $diff = new \Diff($expectedResponse, $actualResponse, array());

            $renderer = new \Diff_Renderer_Text_Unified();
            $this->difference = $difference.$diff->render($renderer);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasError()
    {
        return null !== $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getDifference()
    {
        return $this->difference;
    }
}
