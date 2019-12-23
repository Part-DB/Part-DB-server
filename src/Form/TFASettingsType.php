<?php


namespace App\Form;


use App\Entity\UserSystem\User;
use App\Validator\Constraints\ValidGoogleAuthCode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TFASettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('google_confirmation', TextType::class, [
            'mapped' => false,
            'attr' => ['maxlength' => '6', 'minlength' => '6', 'pattern' => '\d*'],
            'constraints' => [new ValidGoogleAuthCode()]
        ]);

        $builder->add('googleAuthenticatorSecret', HiddenType::class,[
            'disabled' => false,
        ]);


        $builder->add('submit', SubmitType::class);
        $builder->add('cancel', ResetType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
                                   'data_class' => User::class,
                               ]);
    }
}