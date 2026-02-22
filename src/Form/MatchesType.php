<?php

namespace App\Form;

use App\Entity\Matches;
use App\Entity\Tournoi;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MatchesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('equipe1', null, [
                'label' => 'Equipe 1',
                'attr' => ['placeholder' => 'Enter name of Team 1']
            ])
            ->add('equipe2', null, [
                'label' => 'Equipe 2',
                'attr' => ['placeholder' => 'Enter name of Team 2']
            ])
            ->add('score', null, [
                'label' => 'Score',
                'attr' => ['placeholder' => 'Enter match score (e.g. 1-0 or simple int)']
            ])
            ->add('dateMatch', null, [
                'widget' => 'single_text',
                'label' => 'Date du Match',
            ])
            ->add('prix', \Symfony\Component\Form\Extension\Core\Type\EnumType::class, [
                'class' => \App\Entity\Prix::class,
                'label' => 'Prix',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Matches::class,
        ]);
    }
}
