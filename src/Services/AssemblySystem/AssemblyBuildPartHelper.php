<?php

declare(strict_types=1);

namespace App\Services\AssemblySystem;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\Parts\Part;

/**
 * @see \App\Tests\Services\AssemblySystem\AssemblyBuildPartHelperTest
 */
class AssemblyBuildPartHelper
{
    /**
     * Returns a part that represents the builds of a assembly. This part is not saved to the database, and can be used
     * as initial data for the new part form.
     */
    public function getPartInitialization(Assembly $assembly): Part
    {
        $part = new Part();

        //Associate the part with the assembly
        $part->setBuiltAssembly($assembly);

        //Set the name of the part to the name of the assembly
        $part->setName($assembly->getName());

        //Set the description of the part to the description of the assembly
        $part->setDescription($assembly->getDescription());

        //Add a tag to the part that indicates that it is a build part
        $part->setTags('assembly-build');

        //Associate the part with the assembly
        $assembly->setBuildPart($part);

        return $part;
    }
}
