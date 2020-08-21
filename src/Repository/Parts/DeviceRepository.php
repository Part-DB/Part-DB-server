<?php


namespace App\Repository\Parts;


use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Repository\AbstractPartsContainingRepository;

class DeviceRepository extends AbstractPartsContainingRepository
{

    public function getParts(object $element, array $order_by = ['name' => 'ASC']): array
    {
        if (!$element instanceof Device) {
            throw new \InvalidArgumentException('$element must be an Device!');
        }


        //TODO: Change this later, when properly implemented devices
        return [];
    }

    public function getPartsCount(object $element): int
    {
        if (!$element instanceof Device) {
            throw new \InvalidArgumentException('$element must be an Device!');
        }

        //TODO: Change this later, when properly implemented devices
        //Prevent user from deleting devices, to not accidentally remove filled devices from old versions
        return 1;
    }
}