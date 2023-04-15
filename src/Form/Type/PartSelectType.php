<?php

namespace App\Form\Type;

use App\Entity\Parts\Part;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Attachments\PartPreviewGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PartSelectType extends AbstractType implements DataMapperInterface
{
    private UrlGeneratorInterface $urlGenerator;
    private EntityManagerInterface $em;
    private PartPreviewGenerator $previewGenerator;
    private AttachmentURLGenerator $attachmentURLGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator, EntityManagerInterface $em, PartPreviewGenerator $previewGenerator,
        AttachmentURLGenerator $attachmentURLGenerator)
    {
        $this->urlGenerator = $urlGenerator;
        $this->em = $em;
        $this->previewGenerator = $previewGenerator;
        $this->attachmentURLGenerator = $attachmentURLGenerator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        //At initialization we have to fill the form element with our selected data, so the user can see it
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) {
            $form = $event->getForm();
            $config = $form->getConfig()->getOptions();
            $data = $event->getData() ?? [];

            $config['compound'] = false;
            $config['choices'] = is_iterable($data) ? $data : [$data];
            $config['error_bubbling'] = true;

            $form->add('autocomplete', EntityType::class, $config);
        });

        //After form submit, we have to add the selected element as choice, otherwise the form will not accept this element
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function(FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            $options = $form->get('autocomplete')->getConfig()->getOptions();


            if (!isset($data['autocomplete']) || '' === $data['autocomplete']) {
                $options['choices'] = [];
            } else {
                //Extract the ID from the submitted data
                $id = $data['autocomplete'];
                //Find the element in the database
                $element = $this->em->find($options['class'], $id);

                //Add the element as choice
                $options['choices'] = [$element];
                $options['error_bubbling'] = true;
                $form->add('autocomplete', EntityType::class, $options);
            }
        });

       $builder->setDataMapper($this);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => Part::class,
            'choice_label' => 'name',
            //'placeholder' => 'None',
            'compound' => true,
            'error_bubbling' => false,
        ]);

        $resolver->setDefaults([
            'attr' => [
                'data-controller' => 'elements--part-select',
                'data-autocomplete' => $this->urlGenerator->generate('typeahead_parts', ['query' => '__QUERY__']),
                //Disable browser autocomplete
                'autocomplete' => 'off',

            ],
        ]);

        $resolver->setDefaults([
            //Prefill the selected choice with the needed data, so the user can see it without an additional Ajax request
            'choice_attr' => ChoiceList::attr($this, function (?Part $part) {
                if($part) {
                    //Determine the picture to show:
                    $preview_attachment = $this->previewGenerator->getTablePreviewAttachment($part);
                    if ($preview_attachment !== null) {
                        $preview_url = $this->attachmentURLGenerator->getThumbnailURL($preview_attachment,
                            'thumbnail_sm');
                    } else {
                        $preview_url = '';
                    }
                }

                return $part ? [
                    'data-description' => mb_strimwidth($part->getDescription(), 0, 127, '...'),
                    'data-category' => $part->getCategory() ? $part->getCategory()->getName() : '',
                    'data-footprint' => $part->getFootprint() ? $part->getFootprint()->getName() : '',
                    'data-image' => $preview_url,
                ] : [];
            })
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'part_select';
    }

    public function mapDataToForms($data, $forms)
    {
        $form = current(iterator_to_array($forms, false));
        $form->setData($data);
    }

    public function mapFormsToData($forms, &$data)
    {
        $form = current(iterator_to_array($forms, false));
        $data = $form->getData();
    }

}