<?php

namespace App\Form;

use App\Entity\Agent;
use App\Entity\Game;
use App\Entity\GuideVideo;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class GuideVideoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Guide Title',
                'constraints' => [
                    new Assert\NotBlank(message: 'Title is required'),
                    new Assert\Length(['min' => 3, 'max' => 255])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., Astra A-Site Smoke Setup'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (Optional)',
                'required' => false,
                'constraints' => [
                    new Assert\Length(['max' => 1000])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Describe your guide... (max 1000 characters)'
                ]
            ])
            ->add('game', EntityType::class, [
                'label' => 'Game',
                'class' => Game::class,
                'choice_label' => 'name',
                'constraints' => [
                    new Assert\NotNull(message: 'Please select a game')
                ],
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('agent', EntityType::class, [
                'label' => 'Agent/Champion (Optional)',
                'class' => Agent::class,
                'choice_label' => 'name',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('map', ChoiceType::class, [
                'label' => 'Map',
                'choices' => [
                    'All Maps' => 'All',
                    'Ascent' => 'Ascent',
                    'Bind' => 'Bind',
                    'Breeze' => 'Breeze',
                    'Fracture' => 'Fracture',
                    'Haven' => 'Haven',
                    'Lotus' => 'Lotus',
                    'Pearl' => 'Pearl',
                    'Icebox' => 'Icebox',
                    'Split' => 'Split',
                    'Sunset' => 'Sunset',
                    'Abyss' => 'Abyss',
                    'Summoner\'s Rift' => 'Summoner\'s Rift',
                    'Howling Abyss' => 'Howling Abyss',
                    'SR Jungle' => 'SR Jungle',
                    'Kick Off' => 'Kick Off',
                    'Ultimate Team' => 'Ultimate Team',
                    'Career Mode' => 'Career Mode',
                    'Clubs' => 'Clubs',
                    'Battle Royale Island' => 'Battle Royale Island',
                    'Zero Build Island' => 'Zero Build Island',
                    'Reload' => 'Reload',
                    'Creative' => 'Creative',
                ],
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('videoUrl', TextType::class, [
                'label' => 'Video URL',
                'required' => false,
                'constraints' => [
                    new Assert\Url(message: 'Please enter a valid URL')
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://iframe.videodelivery.net/... (optional if file upload)'
                ]
            ])
            ->add('videoFile', FileType::class, [
                'label' => 'Video File (Optional)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '250M',
                        'mimeTypes' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
                        'mimeTypesMessage' => 'Please upload a valid video file (MP4, WEBM, OGG, MOV)',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'video/mp4,video/webm,video/ogg,video/quicktime'
                ]
            ])
            ->add('thumbnail', TextType::class, [
                'label' => 'Thumbnail URL (Optional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://example.com/thumbnail.jpg (optional if file upload)'
                ]
            ])
            ->add('thumbnailFile', FileType::class, [
                'label' => 'Thumbnail Image (Optional)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, WEBP)',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GuideVideo::class,
        ]);
    }
}
