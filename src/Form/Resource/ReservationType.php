<?php

namespace App\Form\Resource;

use App\Entity\Resource\Equipement;
use App\Entity\Resource\ReservationResource;
use App\Entity\Resource\Salle;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('resourceType', ChoiceType::class, [
                'choices' => [
                    'Salle' => 'SALLE',
                    'Équipement' => 'EQUIPEMENT'
                ],
                'placeholder' => 'Sélectionnez un type',
                'required' => true,
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('startTime', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Début',
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('endTime', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Fin',
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('quantity', null, [
                'label' => 'Quantité',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'value' => 1
                ]
            ])
            ->add('salle', EntityType::class, [
                'class' => Salle::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionnez une salle',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('equipement', EntityType::class, [
                'class' => Equipement::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionnez un équipement',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationResource::class,
        ]);
    }
}
