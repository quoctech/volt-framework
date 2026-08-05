<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Volt\Core\Audit\RequestContext;

/**
 * Sinh/nạp request_id (correlation ID) cho mọi request web.
 *
 * - Ưu tiên dùng header X-Request-ID của client nếu hợp lệ.
 * - Ngược lại tự sinh UUID v4 và gắn vào RequestContext để audit/error log dùng chung.
 * - Trả header X-Request-ID trong response để support truy vết chéo.
 */
final class CorrelationFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $requestId = RequestContext::normalize($request->getHeaderLine('X-Request-ID'))
            ?? RequestContext::requestId();

        RequestContext::setRequestId($requestId);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $response->hasHeader('X-Request-ID')) {
            $response->setHeader('X-Request-ID', RequestContext::requestId());
        }

        return null;
    }
}
