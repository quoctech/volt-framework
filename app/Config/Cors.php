<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cross-Origin Resource Sharing (CORS) Configuration
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
 */
class Cors extends BaseConfig
{
    public array $default = [
        'allowedOrigins' => [],
        'allowedOriginsPatterns' => [],
        'supportsCredentials' => true,
        'allowedHeaders' => ['*'],
        'exposedHeaders' => [],
        'allowedMethods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'maxAge' => 7200,
    ];

    public function __construct()
    {
        parent::__construct();

        $originEnv = (string) env('app.cors.allowedOrigins', '');
        if ($originEnv !== '') {
            $origins = array_map('trim', explode(',', $originEnv));
            $this->default['allowedOrigins'] = array_values(array_filter($origins));
        } elseif ($this->default['allowedOrigins'] === []) {
            $baseUrl = (string) env('app.baseURL', 'http://localhost:8080/');
            $parsed = parse_url($baseUrl);
            if (isset($parsed['scheme'], $parsed['host'])) {
                $origin = $parsed['scheme'] . '://' . $parsed['host'];
                if (isset($parsed['port'])) {
                    $origin .= ':' . $parsed['port'];
                }
                $this->default['allowedOrigins'] = [$origin];
            }
        }
    }
}
