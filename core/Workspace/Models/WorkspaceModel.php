<?php

declare(strict_types=1);

namespace Volt\Core\Workspace\Models;

use CodeIgniter\Database\BaseConnection;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Config\Lang\LangService;
use Volt\Core\Database\VoltDatabase;

class WorkspaceModel
{
    private const T_WORKSPACE = 'sys_workspace';

    private readonly BaseConnection $db;

    public function __construct()
    {
        $this->db = VoltDatabase::connection();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUser(string $userName): ?array
    {
        $row = $this->db->table(self::T_WORKSPACE)
            ->where('user_name', $userName)
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Get the user's workspace, auto-creating and seeding it on first visit.
     *
     * @return array<string, mixed>
     */
    public function getOrCreateForUser(UserEntity $user): array
    {
        $userName = (string) $user->name;

        $workspace = $this->findByUser($userName);

        if ($workspace !== null) {
            return $workspace;
        }

        $title = LangService::get('workspace.title', [], LangService::getLang());
        if ($title === 'workspace.title') {
            $title = 'My Workspace';
        }

        $this->db->table(self::T_WORKSPACE)->insert([
            'user_name'  => $userName,
            'title'      => $title,
            'columns'    => 3,
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $workspaceId = (int) $this->db->insertID();
        $workspace   = $this->findByUser($userName);

        $blockModel = new WorkspaceBlockModel();
        $blockModel->seedDefaults($workspaceId, $user->isAdmin());

        return $workspace ?? [
            'id'        => $workspaceId,
            'user_name' => $userName,
            'title'     => 'My Workspace',
            'columns'   => 3,
        ];
    }

    public function updateColumns(int $workspaceId, int $columns): void
    {
        $columns = min(4, max(1, $columns));

        $this->db->table(self::T_WORKSPACE)
            ->where('id', $workspaceId)
            ->update([
                'columns'    => $columns,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function updateTitle(int $workspaceId, string $title): void
    {
        $title = mb_trim($title);
        if ($title === '') {
            return;
        }

        $this->db->table(self::T_WORKSPACE)
            ->where('id', $workspaceId)
            ->update([
                'title'      => mb_substr($title, 0, 100),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
