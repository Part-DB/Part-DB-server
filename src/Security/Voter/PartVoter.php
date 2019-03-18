<?php

namespace App\Security\Voter;

use App\Configuration\PermissionsConfiguration;
use App\Entity\Part;
use App\Entity\User;
use App\Services\PermissionResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * A Voter that votes on Part entities.
 *
 * See parts permissions for valid operations.
 *
 * @package App\Security\Voter
 */
class PartVoter extends Voter
{
    const READ = "read";

    protected $resolver;

    public function __construct(PermissionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    protected function supports($attribute, $subject)
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        //return ($subject instanceof Part || in_array($subject, ['PERM_parts', 'PERM_parts_name']));

        if ($subject instanceof Part)
        {
           return in_array($attribute, $this->resolver->listOperationsForPermission('parts'), false);
        }

        return false;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof User) {
            return false;
        }

        if($subject instanceof Part) {
            //Null concealing operator means, that no
            return $this->resolver->inherit($user, 'parts', $attribute) ?? false;
        }

        //Deny access by default.
        return false;
    }
}
