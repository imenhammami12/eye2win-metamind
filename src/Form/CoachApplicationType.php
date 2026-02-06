<?php
// src/Form/CoachApplicationType.php

namespace App\Form;

use App\Entity\CoachApplication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CoachApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('certifications', TextareaType::class, [
                'label' => 'Certifications and Qualifications',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'List your certifications, ranks, achievements...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please provide your certifications']),
                    new Length([
                        'min' => 50,
                        'minMessage' => 'Describe your certifications in at least {{ limit }} characters',
                    ])
                ],
                'help' => 'Minimum 50 characters'
            ])
            ->add('experience', TextareaType::class, [
                'label' => 'Coaching Experience',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Describe your coaching experience in detail...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please describe your experience']),
                    new Length([
                        'min' => 100,
                        'minMessage' => 'Describe your experience in at least {{ limit }} characters',
                    ])
                ],
                'help' => 'Minimum 100 characters'
            ])
            ->add('cvFileUpload', FileType::class, [
                'label' => 'CV / Portfolio (optional)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid PDF or Word file',
                        'maxSizeMessage' => 'The file is too large ({{ size }} {{ suffix }}). Maximum allowed: {{ limit }} {{ suffix }}.',
                    ])
                ],
                'help' => 'Accepted formats: PDF, DOC, DOCX (max 5MB)'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CoachApplication::class,
        ]);
    }
}