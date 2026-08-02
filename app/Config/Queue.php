<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Queue extends BaseConfig
{
    /** Số lần thử tối đa trước khi chuyển sang dead-letter (status 'dead'). */
    public int $maxAttempts = 3;

    /** Số giây nền cho backoff: available_at = now + base * 2^(attempts-1). */
    public int $backoffBaseSeconds = 5;

    /** Queue mặc định khi dispatch không chỉ định. */
    public string $defaultQueue = 'default';

    /** Timeout mặc định (giây) cho mỗi job khi không khai báo. */
    public int $timeout = 60;

    /** Số giây một job đang 'running' được coi là treo và được claim lại. */
    public int $staleAfterSeconds = 300;

    /** Số giây tối đa worker chạy liên tục trước khi tự thoát (0 = không giới hạn). */
    public int $maxRunSeconds = 0;
}
