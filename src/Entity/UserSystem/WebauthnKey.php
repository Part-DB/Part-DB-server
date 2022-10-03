<?php

namespace App\Entity\UserSystem;

use App\Entity\Base\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
use Webauthn\PublicKeyCredentialSource as BasePublicKeyCredentialSource;

/**
 * @ORM\Table(name="webauthn_keys")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class WebauthnKey extends BasePublicKeyCredentialSource
{
    use TimestampTrait;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected int $id;

    /**
     * @ORM\Column(type="string")
     */
    protected string $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\UserSystem\User", inversedBy="webauthn_keys")
     **/
    protected ?User $user = null;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param  string  $name
     * @return WebauthnKey
     */
    public function setName(string $name): WebauthnKey
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param  User|null  $user
     * @return WebauthnKey
     */
    public function setUser(?User $user): WebauthnKey
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }





    public static function fromRegistration(BasePublicKeyCredentialSource $registration): self
    {
        return new static(
            $registration->getPublicKeyCredentialId(),
            $registration->getType(),
            $registration->getTransports(),
            $registration->getAttestationType(),
            $registration->getTrustPath(),
            $registration->getAaguid(),
            $registration->getCredentialPublicKey(),
            $registration->getUserHandle(),
            $registration->getCounter(),
            $registration->getOtherUI()
        );
    }
}