<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\UserSystem\PermissionData;
use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * The previous migration (Version20260929120000) introduced two new permission operations
 * ("parts_stock.reserve"/"parts_stock.release") and an entirely new permission ("planned_projects"). Existing
 * groups/users have no stored value at all for these (their permissions_data JSON blob predates them), which
 * resolves to INHERIT and is then treated as DENY - silently hiding the "Plan" button and the "Planned Projects"
 * sidebar section for every already-existing installation, even for otherwise fully-privileged admin accounts.
 *
 * This migration backfills sensible values for existing rows, derived from permissions they already have:
 *  - Any holder that already has parts_stock.withdraw or parts_stock.add explicitly allowed also gets
 *    reserve/release allowed (reservations are just another stock operation, like withdraw/add/move/stocktake).
 *  - Any holder's "projects" permission tier (read/edit/create/.../import) is mirrored onto "planned_projects",
 *    since planned projects are modeled directly after, and are exactly as sensitive as, projects.
 * This is done directly via DBAL (not the ORM), reading/writing the permissions_data JSON column, mirroring the
 * approach already used elsewhere in this codebase for permission-data migrations (see WithPermPresetsTrait).
 */
final class Version20260929130000 extends AbstractMultiPlatformMigration
{
    private const PROJECTS_OPERATIONS = ['read', 'edit', 'create', 'delete', 'show_history', 'revert_element', 'import'];

    public function getDescription(): string
    {
        return 'Backfill the new parts_stock.reserve/release and planned_projects permissions onto existing groups and users';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->backfillPermissions();
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->backfillPermissions();
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->backfillPermissions();
    }

    public function mySQLDown(Schema $schema): void
    {
        //Intentionally a no-op: reverting would mean stripping permissions that an administrator might have
        //further customized in the meantime, which is not a safe operation to automate.
    }

    public function sqLiteDown(Schema $schema): void
    {
        //Intentionally a no-op: reverting would mean stripping permissions that an administrator might have
        //further customized in the meantime, which is not a safe operation to automate.
    }

    public function postgreSQLDown(Schema $schema): void
    {
        //Intentionally a no-op: reverting would mean stripping permissions that an administrator might have
        //further customized in the meantime, which is not a safe operation to automate.
    }

    private function backfillPermissions(): void
    {
        foreach (['groups', 'users'] as $table) {
            $rows = $this->connection->fetchAllAssociative(sprintf('SELECT id, permissions_data FROM %s', $table));

            foreach ($rows as $row) {
                if (empty($row['permissions_data'])) {
                    continue;
                }

                $permissionData = PermissionData::fromJSON($row['permissions_data']);
                $changed = false;

                if (true === $permissionData->getPermissionValue('parts_stock', 'withdraw')
                    || true === $permissionData->getPermissionValue('parts_stock', 'add')) {
                    $permissionData->setPermissionValue('parts_stock', 'reserve', true);
                    $permissionData->setPermissionValue('parts_stock', 'release', true);
                    $changed = true;
                }

                foreach (self::PROJECTS_OPERATIONS as $operation) {
                    if (true === $permissionData->getPermissionValue('projects', $operation)) {
                        $permissionData->setPermissionValue('planned_projects', $operation, true);
                        $changed = true;
                    }
                }

                if ($changed) {
                    $this->connection->executeStatement(
                        sprintf('UPDATE %s SET permissions_data = ? WHERE id = ?', $table),
                        [json_encode($permissionData, JSON_THROW_ON_ERROR), $row['id']]
                    );
                }
            }
        }
    }
}
