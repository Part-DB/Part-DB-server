<?php


namespace App\Services\TFA;


use App\Entity\UserSystem\User;

/**
 * This services offers methods to manage backup codes for two factor authentication
 * @package App\Services\TFA
 */
class BackupCodeManager
{
    protected $backupCodeGenerator;

    public function __construct(BackupCodeGenerator $backupCodeGenerator)
    {
        $this->backupCodeGenerator = $backupCodeGenerator;
    }

    /**
     * Enable backup codes for the given user, by generating a set of backup codes.
     * If the backup codes were already enabled before, they a
     * @param  User  $user
     */
    public function enableBackupCodes(User $user)
    {
        if(empty($user->getBackupCodes())) {
           $this->regenerateBackupCodes($user);
        }
    }

    /**
     * Disable (remove) the backup codes when no other 2 factor authentication methods are enabled.
     * @param  User  $user
     */
    public function disableBackupCodesIfUnused(User $user)
    {
        if($user->isU2FAuthEnabled() || $user->isGoogleAuthenticatorEnabled()) {
            return;
        }

        $user->setBackupCodes([]);
    }

    /**
     * Generates a new set of backup codes for the user. If no backup codes were available before, new ones are
     * generated.
     * @param  User  $user The user for which the backup codes should be regenerated
     */
    public function regenerateBackupCodes(User $user)
    {
        $codes = $this->backupCodeGenerator->generateCodeSet();
        $user->setBackupCodes($codes);
    }
}