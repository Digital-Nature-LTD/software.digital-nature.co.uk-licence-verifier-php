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

    private ResponseInterface $response;

    public function __construct(int $status, mixed $body)
    {
        $this->response = new Response(
            $status,
            ['Content-Type' => 'application/json'],
            (string) json_encode($body)
        );
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = [
            'method' => $request->getMethod(),
            'url'    => (string) $request->getUri(),
        ];
        return $this->response;
    }
}
