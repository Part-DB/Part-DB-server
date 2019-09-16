<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\Form\Part;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Entity\PriceInformations\Orderdetail;
use App\Form\AttachmentFormType;
use App\Form\Type\SIUnitType;
use App\Form\Type\StructuralEntityType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\ORM\EntityRepository;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use function foo\func;

class PartBaseType extends AbstractType
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
        /** @var Part $part */
        $part = $builder->getData();

        $status_choices = [
            $this->trans->trans("m_status.unknown") => '',
            $this->trans->trans('m_status.announced') => 'announced',
            $this->trans->trans('m_status.active') => 'active',
            $this->trans->trans('m_status.nrfnd') => 'nrfnd',
            $this->trans->trans('m_status.eol') => 'eol',
            $this->trans->trans('m_status.discontinued') => 'discontinued'
        ];

        //Common section
        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' =>  $this->trans->trans('part.edit.name'),
                'attr' => ['placeholder' => $this->trans->trans('part.edit.name.placeholder')],
                'disabled' => !$this->security->isGranted('name.edit', $part),
            ])
            ->add('description', CKEditorType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => $this->trans->trans('part.edit.description'),
                'config_name' => 'description_config',
                'attr' => ['placeholder' => $this->trans->trans('part.edit.description.placeholder'), 'rows' => 2],
                'disabled' => !$this->security->isGranted('description.edit', $part)
            ])
            ->add('minAmount', SIUnitType::class, [
                'attr' => ['min' => 0, 'placeholder' => $this->trans->trans('part.editmininstock.placeholder')],
                'label' => $this->trans->trans('part.edit.mininstock'),
                'measurement_unit' => $part->getPartUnit(),
                'disabled' => !$this->security->isGranted('minamount.edit', $part),
            ])
            ->add('category', StructuralEntityType::class, [
                'class' => Category::class,
                'label' => $this->trans->trans('part.edit.category'),
                'disable_not_selectable' => true,
                'disabled' => !$this->security->isGranted('category.edit', $part),
            ])
            ->add('footprint', StructuralEntityType::class, [
                'class' => Footprint::class,
                'required' => false,
                'label' => $this->trans->trans('part.edit.footprint'),
                'disable_not_selectable' => true,
                'disabled' => !$this->security->isGranted('footprint.edit', $part),
            ])
            ->add('tags', TextType::class, [
                'required' => false,
                'label' => $this->trans->trans('part.edit.tags'),
                'empty_data' => "",
                'attr' => ['data-role' => 'tagsinput'],
                'disabled' => !$this->security->isGranted('tags.edit', $part)
            ]);

        //Manufacturer section
        $builder->add('manufacturer', StructuralEntityType::class, [
            'class' => Manufacturer::class,
            'required' => false,
            'label' => $this->trans->trans('part.edit.manufacturer.label'),
            'disable_not_selectable' => true,
            'disabled' => !$this->security->isGranted('manufacturer.edit', $part)
            ])
            ->add('manufacturer_product_url', UrlType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => $this->trans->trans('part.edit.manufacturer_url.label'),
                'disabled' => !$this->security->isGranted('mpn.edit', $part),
            ])
            ->add('manufacturer_product_number', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => $this->trans->trans('part.edit.mpn'),
                'disabled' => !$this->security->isGranted('mpn.edit', $part)])
            ->add('manufacturing_status', ChoiceType::class, [
                'label' => $this->trans->trans('part.edit.manufacturing_status'),
                'choices' => $status_choices,
                'required' => false,
                'disabled' => !$this->security->isGranted('status.edit', $part)
            ]);

        //Advanced section
        $builder->add('needsReview', CheckboxType::class, [
            'label_attr' => ['class' => 'checkbox-custom'],
            'required' => false,
            'label' => $this->trans->trans('part.edit.needs_review'),
            'disabled' => !$this->security->isGranted('edit', $part)
        ])
            ->add('favorite', CheckboxType::class, [
                'label_attr' => ['class' => 'checkbox-custom'],
                'required' => false,
                'label' => $this->trans->trans('part.edit.is_favorite'),
                'disabled' => !$this->security->isGranted('change_favorite', $part)
            ])
            ->add('mass', SIUnitType::class, [
                'unit' => 'g',
                'label' => $this->trans->trans('part.edit.mass'),
                'required' => false,
                'disabled' => !$this->security->isGranted('mass.edit', $part)
            ])
            ->add('partUnit', StructuralEntityType::class, [
                'class' => MeasurementUnit::class,
                'required' => false,
                'disable_not_selectable' => true,
                'label' => $this->trans->trans('part.edit.partUnit'),
                'disabled' => !$this->security->isGranted('unit.edit', $part)
            ]);


        //Comment section
        $builder->add('comment', CKEditorType::class, [
            'required' => false,
            'label' => $this->trans->trans('part.edit.comment'),
            'attr' => ['rows' => 4],
            'disabled' => !$this->security->isGranted('comment.edit', $part), 'empty_data' => ''
        ]);

        //Part Lots section
        $builder->add('partLots', CollectionType::class, [
            'entry_type' => PartLotType::class,
            'allow_add' => $this->security->isGranted('lots.create', $part),
            'allow_delete' => $this->security->isGranted('lots.delete', $part),
            'label' => false,
            'entry_options' => [
                'measurement_unit' => $part->getPartUnit(),
                'disabled' => !$this->security->isGranted('lots.edit', $part),
            ],
            'by_reference' => false
        ]);

        //Attachment section
        $builder->add('attachments', CollectionType::class, [
            'entry_type' => AttachmentFormType::class,
            'allow_add' => $this->security->isGranted('attachments.create', $part),
            'allow_delete' => $this->security->isGranted('attachments.delete', $part),
            'label' => false,
            'entry_options' => [
                'data_class' => PartAttachment::class,
                'disabled' => !$this->security->isGranted('attachments.edit', $part),
            ],
            'by_reference' => false
        ]);

        $builder->add('master_picture_attachment', EntityType::class, [
            'required' => false,
            'disabled' => !$this->security->isGranted('attachments.edit', $part),
            'label' => $this->trans->trans('part.edit.master_attachment'),
            'class' => PartAttachment::class,
            'attr' => ['class' => 'selectpicker'],
            'choice_attr' => function ($choice, $key, $value) {
                /** @var Attachment $choice */
                return ['data-subtext' => $choice->getFilename() ?? "URL"];
            },
            'choice_label' => 'name',
            'query_builder' => function (EntityRepository $er) use ($part) {
                if ($part->getID() == null) {
                    //This query is always false, so we get empty results
                    return $er->createQueryBuilder('u')->where('0 = 2');
                }
                return $er->createQueryBuilder('u')
                    ->where('u.element = ?1')
                    ->andWhere("u.path <> ''")
                    ->orderBy('u.name', 'ASC')
                    ->setParameter(1, $part);
            }
        ]);

        //Orderdetails section
        $builder->add('orderdetails', CollectionType::class, [
            'entry_type' => OrderdetailType::class,
            'allow_add' => $this->security->isGranted('orderdetails.create', $part),
            'allow_delete' => $this->security->isGranted('orderdetails.delete', $part),
            'label' => false,
            'by_reference' => false,
            'prototype_data' => new Orderdetail(),
            'entry_options' => [
                'measurement_unit' => $part->getPartUnit(),
                'disabled' => !$this->security->isGranted('orderdetails.edit', $part),
            ]
        ]);

        $builder
            //Buttons
            ->add('save', SubmitType::class, ['label' => $this->trans->trans('part.edit.save')])
            ->add('reset', ResetType::class, ['label' => $this->trans->trans('part.edit.reset')]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Part::class,
        ]);
    }
}
