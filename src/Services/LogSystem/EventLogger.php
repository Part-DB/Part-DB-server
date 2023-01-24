<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\LogSystem;

use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class EventLogger
{
    protected int $minimum_log_level;
    protected array $blacklist;
    protected array $whitelist;
    protected EntityManagerInterface $em;
    protected Security $security;

    public function __construct(int $minimum_log_level, array $blacklist, array $whitelist, EntityManagerInterface $em, Security $security)
    {
        $this->minimum_log_level = $minimum_log_level;
        $this->blacklist = $blacklist;
        $this->whitelist = $whitelist;
        $this->em = $em;
        $this->security = $security;
    }

    /**
     * Adds the given log entry to the Log, if the entry fullfills the global configured criterias.
     * The change will not be flushed yet.
     *
     * @return bool returns true, if the event was added to log
     */
    public function log(AbstractLogEntry $logEntry): bool
    {
        $user = $this->security->getUser();
        //If the user is not specified explicitly, set it to the current user
        if ((null === $user || $user instanceof User) && null === $logEntry->getUser()) {
            if (null === $user) {
                $repo = $this->em->getRepository(User::class);
                $user = $repo->getAnonymousUser();
            }

            //If no anonymous user is available skip the log (needed for data fixtures)
            if (null === $user) {
                return false;
            }
            $logEntry->setUser($user);
        }

        if ($this->shouldBeAdded($logEntry)) {
            $this->em->persist($logEntry);

            return true;
        }

        return false;
    }

    /**
     * Same as log(), but this function can be safely called from within the onFlush() doctrine event, as it
     * updated the changesets of the unit of work.
     * @param  AbstractLogEntry  $logEntry
     * @return bool
     */
    public function logFromOnFlush(AbstractLogEntry $logEntry): bool
    {
        if ($this->log($logEntry)) {
            $uow = $this->em->getUnitOfWork();
            //As we call it from onFlush, we have to recompute the changeset here, according to https://www.doctrine-project.org/projects/doctrine-orm/en/2.14/reference/events.html#reference-events-on-flush
            $uow->computeChangeSet($this->em->getClassMetadata(get_class($logEntry)), $logEntry);

            return true;
        }

        //If the normal log function does not added the log entry, we just do nothing
        return false;
    }

    /**
     * Adds the given log entry to the Log, if the entry fullfills the global configured criterias and flush afterwards.
     *
     * @return bool returns true, if the event was added to log
     */
    public function logAndFlush(AbstractLogEntry $logEntry): bool
    {
        $tmp = $this->log($logEntry);
        $this->em->flush();

        return $tmp;
    }

    public function shouldBeAdded(
        AbstractLogEntry $logEntry,
        ?int $minimum_log_level = null,
        ?array $blacklist = null,
        ?array $whitelist = null
    ): bool {
        //Apply the global settings, if nothing was specified
        $minimum_log_level = $minimum_log_level ?? $this->minimum_log_level;
        $blacklist = $blacklist ?? $this->blacklist;
        $whitelist = $whitelist ?? $this->whitelist;

        //Dont add the entry if it does not reach the minimum level
        if ($logEntry->getLevel() > $minimum_log_level) {
            return false;
        }

        //Check if the event type is black listed
        if (!empty($blacklist) && $this->isObjectClassInArray($logEntry, $blacklist)) {
            return false;
        }

        //Check for whitelisting
        if (!empty($whitelist) && !$this->isObjectClassInArray($logEntry, $whitelist)) {
            return false;
        }

        // By default all things should be added
        return true;
    }

    /**
     * Check if the object type is given in the classes array. This also works for inherited types.
     *
     * @param object   $object  The object which should be checked
     * @param string[] $classes the list of class names that should be used for checking
     */
    protected function isObjectClassInArray(object $object, array $classes): bool
    {
        //Check if the class is directly in the classes array
        if (in_array(get_class($object), $classes, true)) {
            return true;
        }

        //Iterate over all classes and check for inheritance
        foreach ($classes as $class) {
            if (is_a($object, $class)) {
                return true;
            }
        }

        return false;
    }
}
