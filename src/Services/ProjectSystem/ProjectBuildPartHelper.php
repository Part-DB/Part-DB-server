<?php

namespace App\Services\ProjectSystem;

use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;

/**
 * @see \App\Tests\Services\ProjectSystem\ProjectBuildPartHelperTest
 */
class ProjectBuildPartHelper
{
    /**
     * Returns a part that represents the builds of a project. This part is not saved to the database, and can be used
     * as initial data for the new part form.
     */
    public function getPartInitialization(Project $project): Part
    {
        $part = new Part();

        //Associate the part with the project
        $part->setBuiltProject($project);

        //Set the name of the part to the name of the project
        $part->setName($project->getName());

        //Set the description of the part to the description of the project
        $part->setDescription($project->getDescription());

        //Add a tag to the part that indicates that it is a build part
        $part->setTags('project-build');

        //Associate the part with the project
        $project->setBuildPart($part);

        return $part;
    }
}