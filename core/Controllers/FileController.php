<?php

declare(strict_types=1);

namespace Volt\Core\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\Response;
use Config\Services;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Models\FileModel;
use Volt\Core\Security\PermissionResolver;

final class FileController extends Controller
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv', 'application/zip', 'application/gzip',
        'application/json', 'application/xml',
    ];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    private readonly FileModel $fileModel;

    public function __construct()
    {
        $this->fileModel = new FileModel();
    }

    public function upload(): Response
    {
        $user = $this->currentUser();
        if (! $user instanceof UserEntity) {
            return $this->fail('Authentication required.', 401);
        }

        $file = $this->request->getFile('file');
        if (!$file || !$file->isValid()) {
            return $this->fail('No file uploaded or file is invalid.', 400);
        }

        if ($file->getSizeByUnit('b') > self::MAX_FILE_SIZE) {
            return $this->fail('File exceeds maximum size of 10MB.', 413);
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return $this->fail('File type not allowed: ' . $mimeType, 415);
        }

        $attachedToEntity = $this->request->getPost('attached_to_entity');
        $attachedToName   = $this->request->getPost('attached_to_name');
        $attachedToField  = $this->request->getPost('attached_to_field');

        if ($attachedToEntity) {
            if (! $this->canAccessEntity($attachedToEntity, 'write', $user)) {
                return $this->fail('You do not have permission to upload files to this record.', 403);
            }
        }

        $uuid = $this->generateUUID();
        $originalName = $file->getName();
        $extension = $file->getExtension() ? '.' . $file->getExtension() : '';
        $storedName = $uuid . $extension;

        $datePath = date('Y/m');
        $uploadDir = WRITEPATH . 'uploads/' . $datePath;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $filePath = $datePath . '/' . $storedName;
        $destPath = WRITEPATH . 'uploads/' . $filePath;

        if (!$file->move(WRITEPATH . 'uploads/' . $datePath, $storedName, true)) {
            return $this->fail('Failed to store uploaded file.', 500);
        }

        $isPrivate        = (int) ($this->request->getPost('is_private') ?? 1);
        $owner            = $user->name ?? $user->username ?? 'system';

        $thumbnailPath = $this->generateThumbnail($destPath, $mimeType, $datePath, $uuid);

        $record = [
            'name'               => $uuid,
            'file_name'          => $originalName,
            'file_path'          => $filePath,
            'file_size'          => $file->getSizeByUnit('b'),
            'file_type'          => $mimeType,
            'thumbnail_path'     => $thumbnailPath,
            'attached_to_entity' => $attachedToEntity ?: null,
            'attached_to_name'   => $attachedToName ?: null,
            'attached_to_field'  => $attachedToField ?: null,
            'is_private'         => $isPrivate,
            'owner'              => $owner,
        ];

        $this->fileModel->insert($record);

        $this->writeFileAudit('file:upload', $uuid, [
            'file_name' => $originalName,
            'file_size' => $file->getSizeByUnit('b'),
            'file_type' => $mimeType,
            'attached_to_entity' => $attachedToEntity,
            'attached_to_name' => $attachedToName,
        ]);

        return $this->respond([
            'status' => 'ok',
            'message' => 'File uploaded.',
            'data' => $this->fileModel->find($uuid),
        ], 201);
    }

    public function download(string $name): Response
    {
        $user = $this->currentUser();
        if (! $user instanceof UserEntity) {
            return $this->fail('Authentication required.', 401);
        }

        $file = $this->fileModel->find($name);
        if (!$file) {
            return $this->fail('File not found.', 404);
        }

        if (! $this->canAccessFile($file, 'read', $user)) {
            return $this->fail('You do not have permission to download this file.', 403);
        }

        $filePath = WRITEPATH . 'uploads/' . $file['file_path'];
        if (!is_file($filePath)) {
            return $this->fail('File not found on disk.', 404);
        }

        $this->writeFileAudit('file:download', $name, [
            'file_name' => (string) $file['file_name'],
            'file_size' => (int) ($file['file_size'] ?? 0),
            'attached_to_entity' => $file['attached_to_entity'] ?? null,
            'attached_to_name' => $file['attached_to_name'] ?? null,
        ]);

        return $this->response->download($filePath, null)
            ->setFileName($file['file_name'])
            ->setContentType($file['file_type'] ?: 'application/octet-stream')
            ->inline();
    }

    public function delete(string $name): Response
    {
        $user = $this->currentUser();
        if (! $user instanceof UserEntity) {
            return $this->fail('Authentication required.', 401);
        }

        $file = $this->fileModel->find($name);
        if (!$file) {
            return $this->fail('File not found.', 404);
        }

        if (! $this->canAccessFile($file, 'delete', $user)) {
            return $this->fail('You do not have permission to delete this file.', 403);
        }

        $this->fileModel->deleteFileWithRecord($name);

        $this->writeFileAudit('file:delete', $name, [
            'file_name' => (string) $file['file_name'],
            'attached_to_entity' => $file['attached_to_entity'] ?? null,
        ]);

        return $this->respond([
            'status' => 'ok',
            'message' => 'File deleted.',
        ]);
    }

    public function listByEntity(string $entity, string $name, ?string $field = null): Response
    {
        $user = $this->currentUser();
        if (! $user instanceof UserEntity) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->canAccessEntity($entity, 'read', $user)) {
            return $this->fail('You do not have permission to view files for this record.', 403);
        }

        $files = $this->fileModel->findByEntity($entity, $name, $field);

        return $this->respond([
            'status' => 'ok',
            'data' => $files,
        ]);
    }

    private function canAccessFile(array $file, string $action, UserEntity $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $attachedEntity = (string) ($file['attached_to_entity'] ?? '');
        if ($attachedEntity !== '') {
            return $this->canAccessEntity($attachedEntity, $action, $user);
        }

        $isPrivate = (int) ($file['is_private'] ?? 1) === 1;
        $owner = (string) ($file['owner'] ?? '');

        if ($isPrivate) {
            return $owner !== '' && $this->sameActor($owner, $user);
        }

        return true;
    }

    private function canAccessEntity(string $entity, string $action, UserEntity $user): bool
    {
        $normalizedAction = in_array($action, ['create', 'delete'], true) ? 'write' : $action;

        return service('voltPermissionResolver')->can($entity, $normalizedAction, null, null, $user);
    }

    private function sameActor(string $owner, UserEntity $user): bool
    {
        $name = $user->name ?? $user->username ?? '';

        return $owner === $name || $owner === ($user->username ?? '');
    }

    private function currentUser(): ?UserEntity
    {
        try {
            return service('voltAuth')->currentUser();
        } catch (\Throwable) {
            return null;
        }
    }

    private function writeFileAudit(string $action, string $name, array $after = []): void
    {
        $actor = 'system';

        try {
            $actor = service('voltAuth')->currentUser()?->name ?? 'system';
        } catch (\Throwable) {
        }

        service('voltAuditTrailWriter')->write(
            AuditTrailWriter::CAT_FILE,
            $action,
            'sys_file',
            $name,
            [],
            $after,
            $actor,
            ['operation' => 'file'],
        );
    }

    private function generateThumbnail(string $sourcePath, string $mimeType, string $datePath, string $uuid): ?string
    {
        if (!str_starts_with($mimeType, 'image/')) {
            return null;
        }

        try {
            $thumbName = $uuid . '_thumb.webp';
            $thumbPath = $datePath . '/' . $thumbName;
            $destPath = WRITEPATH . 'uploads/' . $thumbPath;

            Services::image()
                ->withFile($sourcePath)
                ->fit(300, 300, 'center')
                ->convert(IMAGETYPE_WEBP)
                ->save($destPath, 80);

            return $thumbPath;
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateUUID(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function respond(array $data, int $statusCode = 200): Response
    {
        return $this->response
            ->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function fail(string $message, int $statusCode = 400): Response
    {
        return $this->response
            ->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setBody(json_encode([
                'status' => 'error',
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
