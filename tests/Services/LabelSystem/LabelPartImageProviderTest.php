<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace App\Tests\Services\LabelSystem;

use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\UserSystem\User;
use App\Services\LabelSystem\LabelPartImageProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class LabelPartImageProviderTest extends KernelTestCase
{
    private LabelPartImageProvider $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(LabelPartImageProvider::class);
        //Images are only embedded, if the user is allowed to view them
        self::loginAs('admin');
    }

    /**
     * Logs in the given user (from the test fixtures), so that permissions are checked for this user
     */
    public static function loginAs(string $username): void
    {
        $user = self::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['name' => $username]);
        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    /**
     * Creates a part with the given picture as master attachment (the builtin footprint images are used, as they always exist)
     */
    public static function createPartWithPicture(string $path = '%FOOTPRINTS%/Active/ICs/IC_DFS.png'): Part
    {
        $part = (new Part())->setName('Test');
        $attachment = (new PartAttachment())->setName('Picture');
        $attachment->setInternalPath($path);
        $part->addAttachment($attachment);
        $part->setMasterPictureAttachment($attachment);

        return $part;
    }

    public function testPartImage(): void
    {
        $image = $this->service->getImage(self::createPartWithPicture());

        $this->assertNotNull($image);
        $this->assertStringStartsWith('data:image/png;base64,', $image['uri']);
        $this->assertSame(320, $image['width']);
        $this->assertSame(240, $image['height']);
    }

    public function testPartLotUsesPartImage(): void
    {
        $lot = (new PartLot())->setPart(self::createPartWithPicture());
        $this->assertSame(320, $this->service->getImage($lot)['width'] ?? null);
    }

    public function testFallbackToFirstPictureAttachment(): void
    {
        //Without a master picture, the first (locally stored) picture attachment is used
        $part = (new Part())->setName('Test');
        $external = (new PartAttachment())->setName('External');
        $external->setExternalPath('https://example.invalid/image.png');
        $part->addAttachment($external);
        $picture = (new PartAttachment())->setName('Main image');
        $picture->setInternalPath('%FOOTPRINTS%/Electromechanics/Connectors/RJxx/SOCKET_RJ11.png');
        $part->addAttachment($picture);

        $this->assertSame(320, $this->service->getImage($part)['width'] ?? null);
    }

    public function testPrivateImageRequiresPermission(): void
    {
        //Private (secure) attachments must only be embedded, if the user is allowed to view them
        $file = 'uploads/_label_part_image_test.png';
        @mkdir(dirname($file), 0777, true);
        copy('public/img/footprints/Active/ICs/IC_DFS.png', $file);

        try {
            $part = self::createPartWithPicture('%SECURE%/_label_part_image_test.png');
            $this->assertNotNull($this->service->getImage($part));

            //The anonymous user can read attachments, but not private ones
            self::loginAs('anonymous');
            $this->assertNull((clone $this->service)->getImage(self::createPartWithPicture('%SECURE%/_label_part_image_test.png')));
        } finally {
            unlink($file);
        }
    }

    public function testNoImage(): void
    {
        //Part without picture
        $this->assertNull($this->service->getImage((new Part())->setName('Test')));
        //Other label targets have no part image
        $this->assertNull($this->service->getImage(new StorageLocation()));
    }

    public function testLargeImagesAreDownscaled(): void
    {
        $file = 'public/media/_label_part_image_test.png';
        @mkdir(dirname($file), 0777, true);
        $image = imagecreatetruecolor(1600, 400);
        imagepng($image, $file);

        try {
            $result = $this->service->getImage(self::createPartWithPicture('%MEDIA%/_label_part_image_test.png'));
            $this->assertSame(600, $result['width'] ?? null);
            $this->assertSame(150, $result['height'] ?? null);
        } finally {
            unlink($file);
        }
    }
}
