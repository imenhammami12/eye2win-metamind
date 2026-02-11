<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'placeholder' => 'votre@email.com',
                    'class' => 'form-control form-control-lg'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Veuillez entrer votre adresse email'
                    ]),
                    new Assert\Email([
                        'message' => 'Adresse email invalide'
                    ])
                ]
            ])
            ->add('notificationChannel', ChoiceType::class, [
                'label' => 'Comment souhaitez-vous recevoir votre code ?',
                'choices' => [
                    '📧 Par email' => 'email',
                    '📱 Par SMS (Twilio)' => 'sms',
                    '💬 Par Telegram (Gratuit)' => 'telegram',
                    // WhatsApp nécessite un compte Twilio payant ou WhatsApp Business API
                    // '📞 Par WhatsApp' => 'whatsapp',
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'email',
                'attr' => [
                    'class' => 'channel-selector'
                ],
                'help' => '💡 En mode développement, les messages seront enregistrés dans les logs',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'password_reset_request',
        ]);
    }
}