<?php

namespace App\Form;

use App\Entity\LiveStream;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class LiveStreamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Stream Title',
                'constraints' => [
                    new NotBlank(message: 'Please enter a title for your stream.'),
                    new Length(min: 3, max: 255, minMessage: 'Title must be at least 3 characters.'),
                ],
                'attr' => [
                    'class'       => 'form-control',
                    'placeholder' => 'e.g. Advanced FPS coaching session',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'rows'        => 4,
                    'placeholder' => 'Describe what viewers will learn...',
                ],
            ])
            ->add('coinPrice', IntegerType::class, [
                'label'       => 'Price (EyeTwin Coins)',
                'constraints' => [
                    new NotBlank(message: 'Please set a price (0 for free).'),
                    new GreaterThanOrEqual(value: 0, message: 'Price cannot be negative.'),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'min'   => 0,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LiveStream::class]);
    }
}