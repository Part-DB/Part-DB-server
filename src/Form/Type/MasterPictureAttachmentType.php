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

namespace App\Form\Type;

use App\Entity\Attachments\Attachment;
use App\Entity\Contracts\HasMasterAttachmentInterface;
use Doctrine\ORM\EntityRepository;
use ReflectionClass;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MasterPictureAttachmentType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('entity');
        $resolver->setAllowedTypes('entity', HasMasterAttachmentInterface::class);

        $resolver->setDefaults([
            'filter' => 'picture',
            'attr' => [
                'class' => 'selectpicker',
            ],
            'choice_attr' => function (Options $options) {
                return  function ($choice, $key, $value) use ($options) {
                    /** @var Attachment $choice */
                    $tmp = ['data-subtext' => $choice->getFilename() ?? 'URL'];

                    if ('picture' === $options['filter'] && ! $choice->isPicture()) {
                        $tmp += ['disabled' => 'disabled'];
                    } elseif ('3d_model' === $options['filter'] && ! $choice->is3DModel()) {
                        $tmp += ['disabled' => 'disabled'];
                    }

                    return $tmp;
                };
            },
            'choice_label' => 'name',
            'class' => function (Options $options) {
                $short_class_name = (new ReflectionClass($options['entity']))->getShortName();
                //Category becomes CategoryAttachment
                return 'App\\Entity\\Attachments\\'.$short_class_name.'Attachment';
            },
            'query_builder' => function (Options $options) {
                return function (EntityRepository $er) use ($options) {
                    $entity = $options['entity'];
                    if (null === $entity->getID()) {
                        //This query is always false, so we get empty results
                        return $er->createQueryBuilder('u')->where('0 = 2');
                    }

                    return $er->createQueryBuilder('u')
                        ->where('u.element = ?1')
                        ->andWhere("u.path <> ''")
                        ->orderBy('u.name', 'ASC')
                        ->setParameter(1, $entity);
                };
            },
        ]);

        $resolver->setAllowedValues('filter', ['', 'picture', '3d_model']);
    }

    public function getParent()
    {
        return EntityType::class;
    }
}
