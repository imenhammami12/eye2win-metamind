<?php

namespace App\Form;

use App\Entity\Channel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChannelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Name',
                'attr' => ['placeholder' => 'Nom du channel'],
            ])
            ->add('description', null, [
                'label' => 'Description',
                'attr' => ['placeholder' => 'Décris ton channel...'],
            ])
            ->add('game', null, [
                'label' => 'Game',
                'attr' => ['placeholder' => 'Ex: Valorant, LoL...'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Public' => Channel::TYPE_PUBLIC,
                    'Private' => Channel::TYPE_PRIVATE,
                ],
                'expanded' => true,   // radios
                'multiple' => false,
                'choice_attr' => fn() => ['class' => 'form-check-inline'], // ✅ side-by-side
            ])
            ->add('imageUrl', null, [
                'label' => 'Image URL (optional)',
                'required' => false,
                'attr' => ['placeholder' => 'URL image (optionnel)'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Channel::class,
        ]);
    }
}
