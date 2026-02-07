<?php

namespace App\Form;

use App\Entity\Video;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class VideoAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'constraints' => [
                    new NotBlank(['message' => 'Le titre est requis.']),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('gameType', TextType::class, [
                'label' => 'Type de jeu',
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'Le type de jeu ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'UPLOADED' => 'UPLOADED',
                    'PROCESSING' => 'PROCESSING',
                    'READY' => 'READY',
                    'FAILED' => 'FAILED',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Video::class,
        ]);
    }
}
