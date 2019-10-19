<?php

namespace App\Form;

use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\User;
use App\Form\Type\CurrencyEntityType;
use App\Form\Type\StructuralEntityType;
use Doctrine\ORM\Query\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
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

    protected $demo_mode;

    public function __construct(Security $security, TranslatorInterface $trans, bool $demo_mode)
    {
        $this->security = $security;
        $this->trans = $trans;
        $this->demo_mode = $demo_mode;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => $this->trans->trans('user.username.label'),
                'disabled' => !$this->security->isGranted('edit_username', $options['data']) || $this->demo_mode,
            ])
            ->add('first_name', TextType::class, [
                'required' => false,
                'label' => $this->trans->trans('user.firstName.label'),
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']) || $this->demo_mode,
            ])
            ->add('last_name', TextType::class, [
                'required' => false,
                'label' => $this->trans->trans('user.lastName.label'),
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']) || $this->demo_mode,
            ])
            ->add('department', TextType::class, [
                'required' => false,
                'label' => $this->trans->trans('user.department.label'),
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']) || $this->demo_mode,
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'label' => $this->trans->trans('user.email.label'),
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']) || $this->demo_mode,
            ])
            ->add('language', LanguageType::class, [
                'disabled' => $this->demo_mode,
                'required' => false,
                'attr' => ['class' => 'selectpicker', 'data-live-search' => true],
                'placeholder' => $this->trans->trans('user_settings.language.placeholder'),
                'label' => $this->trans->trans('user.language_select'),
                'preferred_choices' => ['en', 'de']
                ])
            ->add('timezone', TimezoneType::class, [
                'disabled' => $this->demo_mode,
                'required' => false,
                'attr' => ['class' => 'selectpicker', 'data-live-search' => true],
                'placeholder' => $this->trans->trans('user_settings.timezone.placeholder'),
                'label' => $this->trans->trans('user.timezone.label'),
                'preferred_choices' => ['Europe/Berlin']
                ])
            ->add('theme', ChoiceType::class, [
                'disabled' => $this->demo_mode,
                'required' => false,
                'attr' => ['class' => 'selectpicker'],
                'choices' => User::AVAILABLE_THEMES,
                'choice_label' => function ($entity, $key, $value) {
                    return $value;
                },
                'placeholder' => $this->trans->trans('user_settings.theme.placeholder'),
                'label' => $this->trans->trans('user.theme.label'),
                ])
            ->add('currency', CurrencyEntityType::class, [
                'disabled' => $this->demo_mode,
                'required' => false,
                'label' => $this->trans->trans('user.currency.label')
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
