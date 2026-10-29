<?php

declare(strict_types=1);

namespace Kampusly\Shared\Presentation\Http\Controller;

use Kampusly\Bootstrap\Config;
use Kampusly\Shared\Domain\Exception\ForbiddenException;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class OpenApiDocumentationController
{
    private Config $config;
    private string $specFilePath;

    public function __construct(Config $config, string $specFilePath)
    {
        $this->config = $config;
        $this->specFilePath = $specFilePath;
    }

    public function serveSpec(HttpRequest $request): JsonResponse
    {
        $this->assertDocumentationAccessAllowed();

        if (!is_file($this->specFilePath)) {
            throw new ResourceNotFoundException('OpenAPI specification file was not found.');
        }

        $content = @file_get_contents($this->specFilePath);
        if ($content === false) {
            throw new ResourceNotFoundException('Unable to read OpenAPI specification file.');
        }

        return JsonResponse::raw(
            $content,
            'application/yaml; charset=utf-8',
            200,
            [
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ],
            $request->getRequestId()
        );
    }

    public function serveUi(HttpRequest $request): JsonResponse
    {
        $this->assertDocumentationAccessAllowed();

        $apiPrefix = $this->config->getString('app.api_prefix', '/api/v1');
        $mtime = is_file($this->specFilePath) ? filemtime($this->specFilePath) : time();
        $specUrl = rtrim($apiPrefix, '/') . '/openapi.yaml?v=' . $mtime;
        $appName = htmlspecialchars($this->config->getString('app.name', 'Kampusly'), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{$appName} API Documentation — Swagger UI</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
  <style>
    body { margin: 0; background: #fafafa; }
    .topbar { display: none; }
    .kampusly-banner {
      background: #0f172a;
      color: #f8fafc;
      padding: 12px 24px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .kampusly-banner h1 { margin: 0; font-size: 1.15rem; font-weight: 600; }
    .kampusly-banner span { font-size: 0.85rem; color: #94a3b8; }
  </style>
</head>
<body>
  <div class="kampusly-banner">
    <h1>{$appName} REST API Reference</h1>
    <span>Phase 5 — OpenAPI 3.1</span>
  </div>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" charset="UTF-8"></script>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js" charset="UTF-8"></script>
  <script>
    window.onload = function() {
      SwaggerUIBundle({
        url: '{$specUrl}',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        layout: "BaseLayout"
      });
    };
  </script>
</body>
</html>
HTML;

        return JsonResponse::raw(
            $html,
            'text/html; charset=utf-8',
            200,
            ['Cache-Control' => 'no-cache, no-store, must-revalidate'],
            $request->getRequestId()
        );
    }

    private function assertDocumentationAccessAllowed(): void
    {
        $isProduction = $this->config->getString('app.env') === 'production';
        $allowInProd = $this->config->getBool('app.docs_enabled_in_production', false);

        if ($isProduction && !$allowInProd) {
            throw new ForbiddenException('Interactive API documentation is disabled in the production environment.');
        }
    }
}

