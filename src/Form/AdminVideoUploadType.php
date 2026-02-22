<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AdminVideoUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn(User $user) => sprintf('%s (%s)', $user->getUsername(), $user->getEmail()),
                'label' => 'Utilisateur',
                'placeholder' => 'Sélectionner un utilisateur',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez choisir un utilisateur.']),
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre de la vidéo',
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
                'constraints' => [
                    new NotBlank(['message' => 'Le type de jeu est requis.']),
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'Le type de jeu ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('visibility', ChoiceType::class, [
                'label' => 'Visibilité',
                'mapped' => false,
                'choices' => [
                    'Privé' => 'PRIVATE',
                    'Public' => 'PUBLIC',
                ],
                'data' => 'PRIVATE',
                'expanded' => true,
                'multiple' => false,
                'help' => 'Privé par défaut',
            ])
            ->add('videoFile', FileType::class, [
                'label' => 'Fichier vidéo (MP4)',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '200M',
                        'mimeTypes' => [
                            'video/mp4',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier MP4 valide.',
                        'maxSizeMessage' => 'La vidéo est trop volumineuse ({{ size }} {{ suffix }}). Maximum autorisé : {{ limit }} {{ suffix }}.',
                    ]),
                ],
                'help' => 'Format accepté : MP4 (max 200MB)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
