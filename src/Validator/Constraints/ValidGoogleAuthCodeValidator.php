<?php


namespace App\Validator\Constraints;


use App\Entity\UserSystem\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticator;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValidGoogleAuthCodeValidator extends ConstraintValidator
{

    protected $googleAuthenticator;

    public function __construct(GoogleAuthenticator $googleAuthenticator)
    {
        $this->googleAuthenticator = $googleAuthenticator;
    }

    /**
     * @inheritDoc
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ValidGoogleAuthCode) {
            throw new UnexpectedTypeException($constraint, ValidGoogleAuthCode::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if(!ctype_digit($value)) {
            $this->context->addViolation('validator.google_code.only_digits_allowed');
        }

        //Number must have 6 digits
        if(strlen($value) !== 6) {
            $this->context->addViolation('validator.google_code.wrong_digit_count');
        }

        //Try to retrieve the user we want to check
        if($this->context->getObject() instanceof FormInterface &&
            $this->context->getObject()->getParent() instanceof FormInterface
        && $this->context->getObject()->getParent()->getData() instanceof User) {
            $user = $this->context->getObject()->getParent()->getData();

            //Check if the given code is valid
            if(!$this->googleAuthenticator->checkCode($user, $value)) {
                $this->context->addViolation('validator.google_code.wrong_code');
            }

        }
    }
}