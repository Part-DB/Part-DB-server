<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20250907000000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Create initial admin API token if INITIAL_ADMIN_API_KEY environment variable is set';
    }

    private function createInitialAdminApiToken(): void
    {
        $apiToken = $this->getInitialAdminApiToken();
        if (empty($apiToken)) {
            return;
        }

        // Create a proper API token with the 'tcp_' prefix and the provided key
        $fullToken = 'tcp_' . $apiToken;
        
        // Set expiration to 1 year from now
        $validUntil = date('Y-m-d H:i:s', strtotime('+1 year'));
        $currentDateTime = date('Y-m-d H:i:s');
        
        // Insert the API token for the admin user (user_id = 2)
        // Level 4 = FULL access (can do everything the user can do)
        $sql = "INSERT INTO api_tokens (user_id, name, token, level, valid_until, datetime_added, last_modified) 
                VALUES (2, 'Initial Admin Token', ?, 4, ?, ?, ?)";
        
        $this->addSql($sql, [$fullToken, $validUntil, $currentDateTime, $currentDateTime]);
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->createInitialAdminApiToken();
    }

    public function mySQLDown(Schema $schema): void
    {
        // Remove the initial admin token if it exists
        $this->addSql("DELETE FROM api_tokens WHERE name = 'Initial Admin Token' AND user_id = 2");
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->createInitialAdminApiToken();
    }

    public function sqLiteDown(Schema $schema): void
    {
        // Remove the initial admin token if it exists
        $this->addSql("DELETE FROM api_tokens WHERE name = 'Initial Admin Token' AND user_id = 2");
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->createInitialAdminApiToken();
    }

    public function postgreSQLDown(Schema $schema): void
    {
        // Remove the initial admin token if it exists
        $this->addSql("DELETE FROM api_tokens WHERE name = 'Initial Admin Token' AND user_id = 2");
    }
}