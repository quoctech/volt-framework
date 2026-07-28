<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class DomainBaseURLFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $host = $request->getServer('HTTP_HOST');

        if (! is_string($host) || $host === '') {
            return;
        }

        $config = config(\Config\App::class);
        $scheme = (! empty($request->getServer('HTTPS')) && $request->getServer('HTTPS') !== 'off') ? 'https' : 'http';
        $config->baseURL = "{$scheme}://{$host}/";
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
