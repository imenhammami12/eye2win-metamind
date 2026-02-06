<?php

namespace App\Form;

use App\Entity\Planning;
use App\Entity\PlanningLevel;
use App\Entity\PlanningType as PlanningTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class PlanningType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'class' => PlanningTypeEnum::class,
                'choice_label' => fn ($choice) => $choice->getLabel(),
                'label' => 'Game Type',
                'attr' => ['class' => 'form-select']
            ])
            ->add('level', EnumType::class, [
                'class' => PlanningLevel::class,
                'choice_label' => fn ($choice) => $choice->getLabel(),
                'label' => 'Skill Level',
                'attr' => ['class' => 'form-select']
            ])
            ->add('mode', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'label' => 'Location Mode',
                'mapped' => false,
                'choices' => [
                    'Online' => 'online',
                    'On Site' => 'onsite'
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'online',
                'attr' => ['class' => 'd-flex gap-4']
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Location Description',
                'required' => false, // Handled by JS/Validation
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter address or site details...']
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date',
                'attr' => ['class' => 'form-control']
            ])
            ->add('time', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Time',
                'attr' => ['class' => 'form-control']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Session details, goals, prerequisites...']
            ])
            ->add('image', FileType::class, [
                'label' => 'Cover Image',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotBlank([
                        'message' => 'Please upload a cover image',
                    ]),
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, WEBP)',
                    ])
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('needPartner', CheckboxType::class, [
                'label' => 'Looking for partners?',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Planning::class,
        ]);
    }
}
