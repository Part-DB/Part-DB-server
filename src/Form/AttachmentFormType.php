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

namespace App\Form;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Form\Type\StructuralEntityType;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Validator\Constraints\AllowedFileExtension;
use App\Validator\Constraints\UrlOrBuiltin;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Contracts\Translation\TranslatorInterface;

class AttachmentFormType extends AbstractType
{
    protected AttachmentManager $attachment_helper;
    protected UrlGeneratorInterface $urlGenerator;
    protected bool $allow_attachments_download;
    protected string $max_file_size;
    protected Security $security;
    protected AttachmentSubmitHandler $submitHandler;
    protected TranslatorInterface $translator;

    public function __construct(AttachmentManager $attachmentHelper, UrlGeneratorInterface $urlGenerator,
        Security $security, AttachmentSubmitHandler $submitHandler, TranslatorInterface $translator,
        bool $allow_attachments_downloads, string $max_file_size)
    {
        $this->attachment_helper = $attachmentHelper;
        $this->urlGenerator = $urlGenerator;
        $this->allow_attachments_download = $allow_attachments_downloads;
        $this->security = $security;
        $this->submitHandler = $submitHandler;
        $this->translator = $translator;
        $this->max_file_size = $max_file_size;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'attachment.edit.name',
        ])
            ->add('attachment_type', StructuralEntityType::class, [
                'label' => 'attachment.edit.attachment_type',
                'class' => AttachmentType::class,
                'disable_not_selectable' => true,
                'allow_add' => $this->security->isGranted('@attachment_types.create'),
            ]);

        $builder->add('showInTable', CheckboxType::class, [
            'required' => false,
            'label' => 'attachment.edit.show_in_table',
        ]);

        $builder->add('secureFile', CheckboxType::class, [
            'required' => false,
            'label' => 'attachment.edit.secure_file',
            'mapped' => false,
            'disabled' => !$this->security->isGranted('@attachments.show_private'),
            'help' => 'attachment.edit.secure_file.help',
        ]);

        $builder->add('url', TextType::class, [
            'label' => 'attachment.edit.url',
            'required' => false,
            'attr' => [
                'data-controller' => 'elements--attachment-autocomplete',
                'data-autocomplete' => $this->urlGenerator->generate('typeahead_builtInRessources', ['query' => '__QUERY__']),
                //Disable browser autocomplete
                'autocomplete' => 'off',
            ],
            'help' => 'attachment.edit.url.help',
            'constraints' => [
                $options['allow_builtins'] ? new UrlOrBuiltin() : new Url(),
            ],
        ]);

        $builder->add('downloadURL', CheckboxType::class, [
            'required' => false,
            'label' => 'attachment.edit.download_url',
            'mapped' => false,
            'disabled' => !$this->allow_attachments_download,
        ]);

        $builder->add('file', FileType::class, [
            'label' => 'attachment.edit.file',
            'mapped' => false,
            'required' => false,
            'attr' => [
                /*'class' => 'file',
                'data-show-preview' => 'false',
                'data-show-upload' => 'false',*/
            ],
            'constraints' => [
                //new AllowedFileExtension(),
                new File([
                    'maxSize' => $options['max_file_size'],
                ]),
            ],
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $attachment = $form->getData();

            $file_form = $form->get('file');
            $file = $file_form->getData();

            if ($attachment instanceof Attachment && $file instanceof UploadedFile && $attachment->getAttachmentType(
                ) && !$this->submitHandler->isValidFileExtension($attachment->getAttachmentType(), $file)) {
                $event->getForm()->get('file')->addError(
                    new FormError($this->translator->trans('validator.file_ext_not_allowed'))
                );
            }
        });

        //Check the secure file checkbox, if file is in securefile location
        $builder->get('secureFile')->addEventListener(
            FormEvents::PRE_SET_DATA,
            static function (FormEvent $event): void {
                $attachment = $event->getForm()->getParent()->getData();
                if ($attachment instanceof Attachment) {
                    $event->setData($attachment->isSecure());
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Attachment::class,
            'max_file_size' => $this->max_file_size,
            'allow_builtins' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'attachment';
    }
}
