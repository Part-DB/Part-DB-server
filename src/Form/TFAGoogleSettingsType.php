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
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class TFAGoogleSettingsType extends AbstractType
{

    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) {
            $form = $event->getForm();
            /** @var User $user */
            $user = $event->getData();

            //Only show setup fields, when google authenticator is not enabled
            if(!$user->isGoogleAuthenticatorEnabled()) {
                $form->add(
                    'google_confirmation',
                    TextType::class,
                    [
                        'mapped' => false,
                        'attr' => ['maxlength' => '6', 'minlength' => '6', 'pattern' => '\d*', 'autocomplete' => 'off'],
                        'constraints' => [new ValidGoogleAuthCode()]
                    ]
                );

                $form->add(
                    'googleAuthenticatorSecret',
                    HiddenType::class,
                    [
                        'disabled' => false,
                    ]
                );

                $form->add('submit', SubmitType::class, [
                    'label' => $this->translator->trans('tfa_google.enable')
                ]);
            } else {
                $form->add('submit', SubmitType::class, [
                    'label' => $this->translator->trans('tfa_google.disable'),
                    'attr' => ['class' => 'btn-danger']
                ]);
            }
        });

        //$builder->add('cancel', ResetType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
                                   'data_class' => User::class,
                               ]);
    }
}