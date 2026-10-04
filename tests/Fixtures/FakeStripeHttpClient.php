<?php

namespace Wexample\SymfonyRemotePaymentStripe\Tests\Fixtures;

use Stripe\HttpClient\ClientInterface;

/**
 * Answers Stripe API calls from canned bodies, keyed by "METHOD path".
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<string, list<array>> */
    public array $responses = [];

    /** @var list<array{method: string, path: string, params: array, headers: array}> */
    public array $calls = [];

    public function queue(string $method, string $path, array $body): void
    {
        $this->responses[strtoupper($method).' '.$path][] = $body;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = parse_url($absUrl, PHP_URL_PATH);
        $this->calls[] = ['method' => strtoupper($method), 'path' => $path, 'params' => $params, 'headers' => $headers];
        $key = strtoupper($method).' '.$path;
        $this->responses[$key] ??= [];
        $body = array_shift($this->responses[$key]) ?? ['error' => ['message' => 'No canned response for '.$key]];

        return [json_encode($body), isset($body['error']) ? 404 : 200, []];
    }
}
