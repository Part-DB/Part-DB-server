<?php

namespace App\Form;

use App\Entity\UserSystem\User;
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
use Symfony\Contracts\Translation\TranslatorInterface;

class UserSettingsType extends AbstractType
{
    protected $security;

    protected $trans;

    public function __construct(Security $security, TranslatorInterface $trans)
    {
        $this->security = $security;
        $this->trans = $trans;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => $this->trans->trans('user.username.label'),
                'disabled' => !$this->security->isGranted('edit_username', $options['data']),
            ])
            ->add('first_name', TextType::class, [
                'required' => false,
                'label' => $this->trans->trans('user.firstName.label'),
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']),
            ])
            ->add('last_name', TextType::class, [
                'required' => false,
                'label' => $this->trans->trans('user.lastName.label'),
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']),
            ])
            ->add('department', TextType::class, [
                'required' => false,
                'label' => $this->trans->trans('user.department.label'),
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']),
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'label' => $this->trans->trans('user.email.label'),
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']),
            ])
            ->add('language', LocaleType::class, [
                'required' => false,
                'attr' => ['class' => 'selectpicker', 'data-live-search' => true],
                'placeholder' => $this->trans->trans('user_settings.language.placeholder'),
                'label' => $this->trans->trans('user.language_select'),
                ])
            ->add('timezone', TimezoneType::class, [
                'required' => false,
                'attr' => ['class' => 'selectpicker', 'data-live-search' => true],
                'placeholder' => $this->trans->trans('user_settings.timezone.placeholder'),
                'label' => $this->trans->trans('user.timezone.label'),
                ])
            ->add('theme', ChoiceType::class, [
                'required' => false,
                'placeholder' => $this->trans->trans('user_settings.theme.placeholder'),
                'label' => $this->trans->trans('user.theme.label'),
                ])

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
