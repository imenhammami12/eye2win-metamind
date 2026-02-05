<?php

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
                'label' => 'Certifications et qualifications',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Listez vos certifications, rangs, accomplissements...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez renseigner vos certifications']),
                    new Length([
                        'min' => 50,
                        'minMessage' => 'Décrivez vos certifications en au moins {{ limit }} caractères',
                    ])
                ],
                'help' => 'Minimum 50 caractères'
            ])
            ->add('experience', TextareaType::class, [
                'label' => 'Expérience en coaching',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Décrivez votre expérience de coaching en détail...'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez décrire votre expérience']),
                    new Length([
                        'min' => 100,
                        'minMessage' => 'Décrivez votre expérience en au moins {{ limit }} caractères',
                    ])
                ],
                'help' => 'Minimum 100 caractères'
            ])
            ->add('cvFileUpload', FileType::class, [
                'label' => 'CV / Portfolio (optionnel)',
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
                        'mimeTypesMessage' => 'Veuillez uploader un fichier PDF ou Word valide',
                        'maxSizeMessage' => 'Le fichier est trop volumineux ({{ size }} {{ suffix }}). Maximum autorisé : {{ limit }} {{ suffix }}.',
                    ])
                ],
                'help' => 'Formats acceptés : PDF, DOC, DOCX (max 5MB)'
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