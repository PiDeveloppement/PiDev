<?php

namespace App\Form\Budget;

use App\Entity\Budget\Budget;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BudgetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('eventId', ChoiceType::class, [
                'label' => 'Evenement',
                'choices' => $options['event_choices'],
                'placeholder' => 'Selectionner un evenement',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez selectionner un evenement.'),
                    new Assert\Positive(message: 'Evenement invalide.'),
                ],
            ])
            ->add('initialBudget', NumberType::class, [
                'label' => 'Budget initial (TND)',
                'scale' => 2,
                'html5' => false,
                'invalid_message' => 'Veuillez saisir un budget initial valide.',
                'attr' => [
                    'step' => '0.01',
                    'min' => '0',
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 12000.00',
                    'inputmode' => 'decimal',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le budget initial est obligatoire.'),
                    new Assert\GreaterThan(value: 0, message: 'Le budget initial doit etre strictement positif.'),
                ],
            ])
            ->add('totalRevenue', NumberType::class, [
                'label' => 'Revenu total (TND)',
                'scale' => 2,
                'html5' => false,
                'invalid_message' => 'Veuillez saisir un revenu total valide.',
                'attr' => [
                    'step' => '0.01',
                    'min' => '0',
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 8000.00',
                    'inputmode' => 'decimal',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le revenu total est obligatoire.'),
                    new Assert\PositiveOrZero(message: 'Le revenu total doit etre positif ou nul.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Budget::class,
            'event_choices' => [],
        ]);
    }
}
