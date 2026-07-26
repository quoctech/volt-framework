<?php

declare(strict_types=1);

namespace Volt\Core\System\Handlers;

use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Throwable;

final class VoltExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly ExceptionHandler $inner,
    ) {}

    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        if ($statusCode !== 404) {
            Services::voltErrorLog()->logException(
                $exception,
                ['status_code' => $statusCode, 'exit_code' => $exitCode],
                'exception_handler',
                (string) $statusCode,
            );
        }

        $this->inner->handle($exception, $request, $response, $statusCode, $exitCode);
    }
}
