<?php

namespace App\Security\Voter;

use App\Entity\Part;
use App\Entity\User;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A Voter that votes on Part entities.
 *
 * See parts permissions for valid operations.
 */
class PartVoter extends ExtendedVoter
{
    const READ = 'read';

    protected function supports($attribute, $subject)
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        //return ($subject instanceof Part || in_array($subject, ['PERM_parts', 'PERM_parts_name']));

        if ($subject instanceof Part) {
            //Check if a sub permission should be checked -> $attribute has format name.edit
            if (false !== strpos($attribute, '.')) {
                [$perm, $op] = explode('.', $attribute);

                return in_array($op, $this->resolver->listOperationsForPermission('parts_'.$perm), false);
            }

            return in_array($attribute, $this->resolver->listOperationsForPermission('parts'), false);
        }

        return false;
    }

    protected function voteOnUser($attribute, $subject, User $user): bool
    {
        if ($subject instanceof Part) {
            //Check for sub permissions
            if (false !== strpos($attribute, '.')) {
                [$perm, $op] = explode('.', $attribute);

                return $this->resolver->inherit($user, 'parts_'.$perm, $op) ?? false;
            }

            //Null concealing operator means, that no
            return $this->resolver->inherit($user, 'parts', $attribute) ?? false;
        }

        //Deny access by default.
        return false;
    }
}
