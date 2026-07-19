<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Auth\Services\AuthService;
use Volt\Core\Engine\VoltMetadataCompiler;
use Volt\Core\Security\PermissionResolver;
use Volt\Core\System\Services\SystemStatusService;
use Volt\Core\Validation\MetadataValidator;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    public static function voltMetadataCompiler(?bool $getShared = true): VoltMetadataCompiler
    {
        if ($getShared) {
            return static::getSharedInstance('voltMetadataCompiler');
        }

        return new VoltMetadataCompiler();
    }

    public static function voltAuth(?bool $getShared = true): AuthService
    {
        if ($getShared) {
            return static::getSharedInstance('voltAuth');
        }

        return new AuthService();
    }

    public static function voltMetadataValidator(?bool $getShared = true): MetadataValidator
    {
        if ($getShared) {
            return static::getSharedInstance('voltMetadataValidator');
        }

        return new MetadataValidator();
    }

    public static function voltPermissionResolver(?bool $getShared = true): PermissionResolver
    {
        if ($getShared) {
            return static::getSharedInstance('voltPermissionResolver');
        }

        return new PermissionResolver();
    }

    public static function voltAuditTrailWriter(?bool $getShared = true): AuditTrailWriter
    {
        if ($getShared) {
            return static::getSharedInstance('voltAuditTrailWriter');
        }

        return new AuditTrailWriter();
    }

    public static function voltSystemStatus(?bool $getShared = true): SystemStatusService
    {
        if ($getShared) {
            return static::getSharedInstance('voltSystemStatus');
        }

        return new SystemStatusService();
    }
}
