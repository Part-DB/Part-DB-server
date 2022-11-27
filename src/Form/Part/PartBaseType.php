<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Form\Part;

use App\Entity\Attachments\PartAttachment;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Orderdetail;
use App\Form\AttachmentFormType;
use App\Form\ParameterType;
use App\Form\Type\MasterPictureAttachmentType;
use App\Form\Type\RichTextEditorType;
use App\Form\Type\SIUnitType;
use App\Form\Type\StructuralEntityType;
use App\Form\WorkaroundCollectionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class PartBaseType extends AbstractType
{
    protected Security $security;
    protected UrlGeneratorInterface $urlGenerator;

    public function __construct(Security $security, UrlGeneratorInterface $urlGenerator)
    {
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Part $part */
        $part = $builder->getData();

        $status_choices = [
            'm_status.unknown' => '',
            'm_status.announced' => 'announced',
            'm_status.active' => 'active',
            'm_status.nrfnd' => 'nrfnd',
            'm_status.eol' => 'eol',
            'm_status.discontinued' => 'discontinued',
        ];

        //Common section
        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' => 'part.edit.name',
                'attr' => [
                    'placeholder' => 'part.edit.name.placeholder',
                ],
            ])
            ->add('description', RichTextEditorType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'part.edit.description',
                'mode' => 'markdown-single_line',
                'attr' => [
                    'placeholder' => 'part.edit.description.placeholder',
                    'rows' => 2,
                ],
            ])
            ->add('minAmount', SIUnitType::class, [
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'part.editmininstock.placeholder',
                ],
                'label' => 'part.edit.mininstock',
                'measurement_unit' => $part->getPartUnit(),
            ])
            ->add('category', StructuralEntityType::class, [
                'class' => Category::class,
                'label' => 'part.edit.category',
                'disable_not_selectable' => true,
                'constraints' => [
                ],
            ])
            ->add('footprint', StructuralEntityType::class, [
                'class' => Footprint::class,
                'required' => false,
                'label' => 'part.edit.footprint',
                'disable_not_selectable' => true,
            ])
            ->add('tags', TextType::class, [
                'required' => false,
                'label' => 'part.edit.tags',
                'empty_data' => '',
                'attr' => [
                    'class' => 'tagsinput',
                    'data-controller' => 'elements--tagsinput',
                    'data-autocomplete' => $this->urlGenerator->generate('typeahead_tags', ['query' => '__QUERY__']),
                ],
            ]);

        //Manufacturer section
        $builder->add('manufacturer', StructuralEntityType::class, [
            'class' => Manufacturer::class,
            'required' => false,
            'label' => 'part.edit.manufacturer.label',
            'disable_not_selectable' => true,
        ])
            ->add('manufacturer_product_url', UrlType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'part.edit.manufacturer_url.label',
            ])
            ->add('manufacturer_product_number', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'part.edit.mpn',
            ])
            ->add('manufacturing_status', ChoiceType::class, [
                'label' => 'part.edit.manufacturing_status',
                'choices' => $status_choices,
                'required' => false,
            ]);

        //Advanced section
        $builder->add('needsReview', CheckboxType::class, [
            'required' => false,
            'label' => 'part.edit.needs_review',
        ])
            ->add('favorite', CheckboxType::class, [
                'required' => false,
                'label' => 'part.edit.is_favorite',
                'disabled' => !$this->security->isGranted('change_favorite', $part),
            ])
            ->add('mass', SIUnitType::class, [
                'unit' => 'g',
                'label' => 'part.edit.mass',
                'required' => false,
            ])
            ->add('partUnit', StructuralEntityType::class, [
                'class' => MeasurementUnit::class,
                'required' => false,
                'disable_not_selectable' => true,
                'label' => 'part.edit.partUnit',
            ]);

        //Comment section
        $builder->add('comment', RichTextEditorType::class, [
            'required' => false,
            'label' => 'part.edit.comment',
            'attr' => [
                'rows' => 4,
            ],
            'mode' => 'markdown-full',
            'empty_data' => '',
        ]);

        //Part Lots section
        $builder->add('partLots', CollectionType::class, [
            'entry_type' => PartLotType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'reindex_enable' => true,
            'label' => false,
            'entry_options' => [
                'measurement_unit' => $part->getPartUnit(),
            ],
            'by_reference' => false,
        ]);

        //Attachment section
        $builder->add('attachments', CollectionType::class, [
            'entry_type' => AttachmentFormType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'reindex_enable' => true,
            'label' => false,
            'entry_options' => [
                'data_class' => PartAttachment::class,
            ],
            'by_reference' => false,
        ]);

        $builder->add('master_picture_attachment', MasterPictureAttachmentType::class, [
            'required' => false,
            'label' => 'part.edit.master_attachment',
            'entity' => $part,
        ]);

        //Orderdetails section
        $builder->add('orderdetails', CollectionType::class, [
            'entry_type' => OrderdetailType::class,
            'reindex_enable' => true,
            'allow_add' => true,
            'allow_delete' => true,
            'label' => false,
            'by_reference' => false,
            'prototype_data' => new Orderdetail(),
            'entry_options' => [
                'measurement_unit' => $part->getPartUnit(),
            ],
        ]);

        $builder->add('parameters', CollectionType::class, [
            'entry_type' => ParameterType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'label' => false,
            'reindex_enable' => true,
            'by_reference' => false,
            'prototype_data' => new PartParameter(),
            'entry_options' => [
                'data_class' => PartParameter::class,
            ],
        ]);

        $builder->add('log_comment', TextType::class, [
            'label' => 'edit.log_comment',
            'mapped' => false,
            'required' => false,
            'empty_data' => null,
        ]);

        $builder
            //Buttons
            ->add('save', SubmitType::class, [
                'label' => 'part.edit.save',
                'attr' => [
                    'value' => 'save'
                ]
            ])
            ->add('save_and_clone', SubmitType::class, [
                'label' => 'part.edit.save_and_clone',
                'attr' => [
                    'value' => 'save-and-clone'
                ]
            ])
            ->add('reset', ResetType::class, ['label' => 'part.edit.reset']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Part::class,
        ]);
    }
}
