<?php

declare(strict_types=1);

namespace Volt\Core\System\Handlers;

use CodeIgniter\Debug\ExceptionHandler;

/**
 * Sửa bug framework: maskSensitiveData truy cập $line['args'] mà không kiểm tra
 * tồn tại, gây crash khi có frame trace thiếu 'args' (vd shutdown handler).
 */
final class VoltMaskingExceptionHandler extends ExceptionHandler
{
    protected function maskSensitiveData(array $trace, array $keysToMask, string $path = ''): array
    {
        foreach ($trace as $i => $line) {
            if (isset($line['args']) && is_array($line['args'])) {
                $trace[$i]['args'] = $this->maskData($line['args'], $keysToMask);
            }
        }

        return $trace;
    }
}