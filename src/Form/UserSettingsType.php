<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class UserSettingsType extends AbstractType
{
    protected $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'user.username.label',
                'disabled' => !$this->security->isGranted('edit_username', $options['data']), ])
            ->add('first_name', TextType::class, ['required' => false,
                'label' => 'user.firstName.label',
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']), ])
            ->add('last_name', TextType::class, ['required' => false,
                'label' => 'user.lastName.label',
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']), ])
            ->add('department', TextType::class, ['required' => false,
                'label' => 'user.department.label',
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']), ])
            ->add('email', EmailType::class, ['required' => false,
                'label' => 'user.email.label',
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']), ])
            ->add('language', LocaleType::class, ['required' => false,
                'attr' => ['class' => 'selectpicker', 'data-live-search' => true], 'placeholder' => 'user_settings.language.placeholder', 'label' => 'user.language_select', ])
            ->add('timezone', TimezoneType::class, ['required' => false,
                'attr' => ['class' => 'selectpicker', 'data-live-search' => true],
                'placeholder' => 'user_settings.timezone.placeholder', 'label' => 'user.timezone.label', ])
            ->add('theme', ChoiceType::class, ['required' => false,
                'placeholder' => 'user_settings.theme.placeholder', 'label' => 'user.theme.label', ])

            //Buttons
            ->add('save', SubmitType::class, ['label' => 'save'])
            ->add('reset', ResetType::class, ['label' => 'reset']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
