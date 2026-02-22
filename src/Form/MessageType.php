<?php

namespace App\Form;

use App\Entity\Channel;
use App\Entity\Message;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;


class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'required' => false,      // ✅ allow empty text
                'empty_data' => '',       // ✅ prevents null going into setContent(string)
            ])
            ->add('files', FileType::class, [
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    // ✅ avoid spam uploads
                    new Count([
                        'max' => 5,
                        'maxMessage' => 'You can attach up to {{ limit }} files.',
                    ]),
                    new Assert\All([
                        'constraints' => [
                            new Assert\File([
                                'maxSize' => '10M',
                                'mimeTypes' => [
                                    'image/*',
                                    'application/pdf',
                                    'text/plain',
                                    'application/zip',
                                    'application/x-zip-compressed',
                                ],
                                'mimeTypesMessage' => 'Invalid file type.',
                            ])
                        ]
                    ])
                ],
            ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Message::class,
        ]);
    }
}
