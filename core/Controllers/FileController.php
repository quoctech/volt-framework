<?php

declare(strict_types=1);

namespace Volt\Core\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\Response;
use Volt\Core\Models\FileModel;

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

        $attachedToEntity = $this->request->getPost('attached_to_entity');
        $attachedToName   = $this->request->getPost('attached_to_name');
        $attachedToField  = $this->request->getPost('attached_to_field');
        $isPrivate        = (int) ($this->request->getPost('is_private') ?? 1);
        $owner            = session()->get('user_name') ?? 'system';

        $record = [
            'name'               => $uuid,
            'file_name'          => $originalName,
            'file_path'          => $filePath,
            'file_size'          => $file->getSizeByUnit('b'),
            'file_type'          => $mimeType,
            'attached_to_entity' => $attachedToEntity ?: null,
            'attached_to_name'   => $attachedToName ?: null,
            'attached_to_field'  => $attachedToField ?: null,
            'is_private'         => $isPrivate,
            'owner'              => $owner,
        ];

        $this->fileModel->insert($record);

        return $this->respond([
            'status' => 'ok',
            'message' => 'File uploaded.',
            'data' => $this->fileModel->find($uuid),
        ], 201);
    }

    public function download(string $name): Response
    {
        $file = $this->fileModel->find($name);
        if (!$file) {
            return $this->fail('File not found.', 404);
        }

        $filePath = WRITEPATH . 'uploads/' . $file['file_path'];
        if (!is_file($filePath)) {
            return $this->fail('File not found on disk.', 404);
        }

        $this->response
            ->setHeader('Content-Type', $file['file_type'] ?: 'application/octet-stream')
            ->setHeader('Content-Disposition', 'inline; filename="' . $file['file_name'] . '"')
            ->setHeader('Content-Length', (string) $file['file_size'])
            ->setBody(file_get_contents($filePath));

        return $this->response;
    }

    public function delete(string $name): Response
    {
        $file = $this->fileModel->find($name);
        if (!$file) {
            return $this->fail('File not found.', 404);
        }

        $this->fileModel->deleteFileWithRecord($name);

        return $this->respond([
            'status' => 'ok',
            'message' => 'File deleted.',
        ]);
    }

    public function listByEntity(string $entity, string $name, ?string $field = null): Response
    {
        $files = $this->fileModel->findByEntity($entity, $name, $field);

        return $this->respond([
            'status' => 'ok',
            'data' => $files,
        ]);
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
