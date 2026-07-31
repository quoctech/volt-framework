<?php

declare(strict_types=1);

namespace Volt\Core\Workspace\Models;

use CodeIgniter\Database\BaseConnection;
use Volt\Core\Config\Lang\LangService;
use Volt\Core\Database\VoltDatabase;

class WorkspaceBlockModel
{
    private const T_BLOCK = 'sys_workspace_block';

    private readonly BaseConnection $db;

    public function __construct()
    {
        $this->db = VoltDatabase::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForWorkspace(int $workspaceId): array
    {
        $rows = $this->db->table(self::T_BLOCK)
            ->where('workspace_id', $workspaceId)
            ->where('is_visible', 1)
            ->orderBy('sort', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(function (array $row): array {
            $row['data'] = $this->decodeJsonObject($row['data'] ?? '{}');
            $row['size'] = min(3, max(1, (int) ($row['size'] ?? 1)));

            return $row;
        }, is_array($rows) ? $rows : []);
    }

    public function findById(int $blockId): ?array
    {
        $row = $this->db->table(self::T_BLOCK)
            ->where('id', $blockId)
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            return null;
        }

        $row['data'] = $this->decodeJsonObject($row['data'] ?? '{}');

        return $row;
    }

    public function upsert(int $workspaceId, int $blockId, array $payload): array
    {
        $blockType = mb_trim((string) ($payload['block_type'] ?? ''));
        if (! in_array($blockType, ['shortcut', 'note', 'entity_list', 'count'], true)) {
            throw new \InvalidArgumentException('workspace.invalid_block_type');
        }

        $title = mb_substr(mb_trim((string) ($payload['title'] ?? '')), 0, 255);
        $size  = min(3, max(1, (int) ($payload['size'] ?? 1)));
        $data  = $this->normalizeData($blockType, $payload['data'] ?? []);

        $fields = [
            'block_type' => $blockType,
            'title'      => $title,
            'data'       => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'size'       => $size,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($blockId > 0) {
            $this->db->table(self::T_BLOCK)
                ->where('id', $blockId)
                ->where('workspace_id', $workspaceId)
                ->update($fields);

            if ($this->db->affectedRows() === 0) {
                throw new \InvalidArgumentException('workspace.block_not_found');
            }

            return $this->findById($blockId) ?? [];
        }

        $maxSortRow = $this->db->table(self::T_BLOCK)
            ->selectMax('sort')
            ->where('workspace_id', $workspaceId)
            ->get()
            ->getRowArray();
        $sort = (int) ($maxSortRow['sort'] ?? 0);

        $fields['workspace_id'] = $workspaceId;
        $fields['sort']         = $sort + 1;
        $fields['is_visible']   = 1;
        $fields['created_at']   = date('Y-m-d H:i:s');

        $this->db->table(self::T_BLOCK)->insert($fields);

        return $this->findById((int) $this->db->insertID()) ?? [];
    }

    public function delete(int $workspaceId, int $blockId): void
    {
        $this->db->table(self::T_BLOCK)
            ->where('id', $blockId)
            ->where('workspace_id', $workspaceId)
            ->delete();
    }

    /**
     * @param list<int> $orderedIds
     */
    public function reorder(int $workspaceId, array $orderedIds): void
    {
        $orderedIds = array_values(array_filter(
            $orderedIds,
            static fn (mixed $blockId): bool => (int) $blockId > 0
        ));

        if ($orderedIds === []) {
            return;
        }

        $cases = [];
        foreach ($orderedIds as $sort => $blockId) {
            $cases[] = 'WHEN ' . (int) $blockId . ' THEN ' . (int) $sort;
        }

        $placeholders = implode(',', array_fill(0, count($orderedIds), '?'));

        $this->db->query(
            'UPDATE ' . $this->db->protectIdentifiers(self::T_BLOCK)
            . ' SET sort = CASE id ' . implode(' ', $cases) . ' ELSE sort END, updated_at = ?'
            . ' WHERE workspace_id = ? AND id IN (' . $placeholders . ')',
            array_merge([date('Y-m-d H:i:s'), $workspaceId], $orderedIds)
        );
    }

    public function seedDefaults(int $workspaceId, bool $isAdmin): void
    {
        $lang = LangService::getLang();

        $t = static fn (string $key, string $fallback): string => LangService::get($key, [], $lang);

        $blocks = [
            [
                'block_type' => 'shortcut',
                'title'      => $t('workspace.shortcut_entities', 'Entities'),
                'data'       => ['url' => '/desk/entities', 'icon' => 'doc'],
            ],
        ];

        if ($isAdmin) {
            $adminBlocks = [
                ['block_type' => 'shortcut', 'title' => $t('workspace.shortcut_users', 'Users'), 'data' => ['url' => '/desk/users', 'icon' => 'user']],
                ['block_type' => 'shortcut', 'title' => $t('workspace.shortcut_roles', 'Roles'), 'data' => ['url' => '/desk/roles', 'icon' => 'shield']],
                ['block_type' => 'shortcut', 'title' => $t('workspace.shortcut_tenants', 'Tenants'), 'data' => ['url' => '/desk/tenants', 'icon' => 'server']],
                ['block_type' => 'shortcut', 'title' => $t('workspace.shortcut_reports', 'Reports'), 'data' => ['url' => '/desk/reports', 'icon' => 'chart']],
            ];

            $blocks = array_merge($blocks, $adminBlocks);
        }

        $blocks[] = [
            'block_type' => 'note',
            'title'      => $t('workspace.welcome', 'Welcome'),
            'data'       => ['text' => $t('workspace.welcome_text', 'Welcome to your Workspace. Click "Customize" to add shortcuts, notes, entity lists and counters.')],
        ];

        $sort = 0;
        foreach ($blocks as $block) {
            $this->db->table(self::T_BLOCK)->insert([
                'workspace_id' => $workspaceId,
                'block_type'   => $block['block_type'],
                'title'        => $block['title'],
                'data'         => json_encode($block['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'size'         => $block['block_type'] === 'note' ? 3 : 1,
                'sort'         => $sort,
                'is_visible'   => 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            $sort++;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeData(string $blockType, mixed $data): array
    {
        $data = is_array($data) ? $data : [];

        return match ($blockType) {
            'shortcut' => [
                'url'  => mb_trim((string) ($data['url'] ?? '')),
                'icon' => mb_trim((string) ($data['icon'] ?? '')),
            ],
            'note' => [
                'text' => (string) ($data['text'] ?? ''),
            ],
            'entity_list' => [
                'entity'   => mb_trim((string) ($data['entity'] ?? '')),
                'max_rows' => min(5, max(1, (int) ($data['max_rows'] ?? 5))),
            ],
            'count' => [
                'entity' => mb_trim((string) ($data['entity'] ?? '')),
            ],
            default => [],
        };
    }

    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
