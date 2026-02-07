<?php

namespace App\Form;

use App\Entity\Tournoi;
use App\Entity\TypeTournoi;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class TournoiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du Tournoi',
                'attr' => [
                    'placeholder' => 'ex: Championnat d\'été 2024',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a name',
                    ]),
                ],
            ])
            ->add('typeTournoi', EnumType::class, [
                'class' => TypeTournoi::class,
                'label' => 'Type de Tournoi',
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn ($choice) => $choice->value,
            ])
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de Début',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select a start date',
                    ]),
                ],
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de Fin',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select an end date',
                    ]),
                ],
            ])
            ->add('description', \Symfony\Component\Form\Extension\Core\Type\TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description du tournoi...',
                    'class' => 'form-control',
                    'rows' => 4,
                ],
            ])
            ->add('image', FileType::class, [
                'label' => 'Logo du Tournoi (Image file)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, WEBP)',
                    ])
                ],
                'attr' => ['class' => 'form-control'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tournoi::class,
        ]);
    }
}
