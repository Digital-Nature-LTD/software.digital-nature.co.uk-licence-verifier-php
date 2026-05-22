<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Tests;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MockHttpClient implements ClientInterface
{
    /** @var array<array{method: string, url: string}> */
    public array $requests = [];

    /** @var ResponseInterface[] */
    private array $responses;

    private int $index = 0;

    public function __construct(ResponseInterface ...$responses)
    {
        $this->responses = $responses;
    }

    /**
     * @param int   $status
     * @param mixed $body
     */
    public static function responding(int $status, $body): self
    {
        return new self(new Response(
            $status,
            ['Content-Type' => 'application/json'],
            (string) json_encode($body)
        ));
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = [
            'method' => $request->getMethod(),
            'url'    => (string) $request->getUri(),
        ];

        $response = $this->responses[$this->index] ?? end($this->responses);
        $this->index++;

        return $response;
    }
}
