<?php

namespace App\Tests\Services\TFA;

use App\Entity\UserSystem\U2FKey;
use App\Entity\UserSystem\User;
use App\Services\TFA\BackupCodeManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BackupCodeManagerTest extends WebTestCase
{
    /**
     * @var BackupCodeManager $service
     */
    protected $service;

    public function setUp(): void
    {
        self::bootKernel();
        $this->service = self::$container->get(BackupCodeManager::class);
    }

    public function testRegenerateBackupCodes()
    {
        $user = new User();
        $old_codes = ['aaaa', 'bbbb'];
        $user->setBackupCodes($old_codes);
        $this->service->regenerateBackupCodes($user);
        $this->assertNotEquals($old_codes, $user->getBackupCodes());
    }

    public function testEnableBackupCodes()
    {
        $user = new User();
        //Check that nothing is changed, if there are already backup codes

        $old_codes = ['aaaa', 'bbbb'];
        $user->setBackupCodes($old_codes);
        $this->service->enableBackupCodes($user);
        $this->assertEquals($old_codes, $user->getBackupCodes());

        //When no old codes are existing, it should generate a set
        $user->setBackupCodes([]);
        $this->service->enableBackupCodes($user);
        $this->assertNotEmpty($user->getBackupCodes());
    }

    public function testDisableBackupCodesIfUnused()
    {
        $user = new User();

        //By default nothing other 2FA is activated, so the backup codes should be disabled
        $codes = ['aaaa', 'bbbb'];
        $user->setBackupCodes($codes);
        $this->service->disableBackupCodesIfUnused($user);
        $this->assertEmpty($user->getBackupCodes());

        $user->setBackupCodes($codes);

        $user->setGoogleAuthenticatorSecret('jskf');
        $this->service->disableBackupCodesIfUnused($user);
        $this->assertEquals($codes, $user->getBackupCodes());

        $user->setGoogleAuthenticatorSecret('');
        $user->addU2FKey(new U2FKey());
        $this->service->disableBackupCodesIfUnused($user);
        $this->assertEquals($codes, $user->getBackupCodes());
    }
}
