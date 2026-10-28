<?php

declare(strict_types=1);

namespace Kampusly\Tests\Feature\Api;

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Tests\Support\TestCase;

final class HealthEndpointTest extends TestCase
{
    public function testApiRootAndLiveHealthEndpointReturnExpectedJsonContracts(): void
    {
        $app = ApplicationFactory::create(dirname(__DIR__, 3));

        $rootRequest = new HttpRequest('GET', '/api/v1', [], [], [], 'req_root_01');
        $rootResponse = $app->handle($rootRequest);

        $this->assertSame(200, $rootResponse->getStatusCode());
        $rootData = $rootResponse->getData();
        $this->assertSame('kampusly-api', $rootData['service']);
        $this->assertSame('/api/v1', $rootData['api_prefix']);

        $liveRequest = new HttpRequest(
            'GET',
            '/api/v1/health/live',
            [],
            ['Origin' => 'http://localhost:5173'],
            [],
            'req_live_01'
        );
        $liveResponse = $app->handle($liveRequest);

        $this->assertSame(200, $liveResponse->getStatusCode());
        $liveData = $liveResponse->getData();
        $this->assertSame('ok', $liveData['status']);
        $this->assertSame('kampusly-api', $liveData['service']);
        $this->assertArrayHasKey('timestamp', $liveData);
        $this->assertSame(
            'http://localhost:5173',
            $liveResponse->getHeaders()['Access-Control-Allow-Origin'] ?? null
        );
    }

    public function testUnknownEndpointReturnsStandardNotFoundJsonEnvelope(): void
    {
        $app = ApplicationFactory::create(dirname(__DIR__, 3));
        $request = new HttpRequest('GET', '/api/v1/does-not-exist', [], [], [], 'req_404_01');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $data = $response->getData();
        $this->assertSame('RESOURCE_NOT_FOUND', $data['code']);
        $this->assertSame('req_404_01', $data['request_id']);
    }
}

