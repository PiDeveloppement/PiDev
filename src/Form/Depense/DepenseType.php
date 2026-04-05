<?php

namespace App\Form\Depense;

use App\Entity\Depense\Depense;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DepenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $budgetFieldOptions = [
            'label' => 'Budget',
            'choices' => $options['budget_choices'],
            'placeholder' => 'Selectionner un budget',
            'mapped' => false,
            'attr' => ['class' => 'form-control'],
            'constraints' => [
                new Assert\NotBlank(message: 'Veuillez selectionner un budget.'),
                new Assert\Positive(message: 'Budget invalide.'),
            ],
        ];

        if ($options['selected_budget_id'] !== null) {
            $budgetFieldOptions['data'] = (int) $options['selected_budget_id'];
        }

        $builder
            ->add('budgetId', ChoiceType::class, $budgetFieldOptions)
            ->add('description', TextType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Description de la depense',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La description est obligatoire.'),
                    new Assert\Length(
                        min: 3,
                        max: 255,
                        minMessage: 'La description est trop courte.',
                        maxMessage: 'La description est trop longue.'
                    ),
                ],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Categorie',
                'choices' => $options['category_choices'],
                'placeholder' => 'Selectionner une categorie',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez selectionner une categorie.'),
                ],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant',
                'scale' => 2,
                'attr' => [
                    'step' => '0.01',
                    'min' => '0',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le montant est obligatoire.'),
                    new Assert\GreaterThan(value: 0, message: 'Le montant doit etre strictement positif.'),
                ],
            ])
            ->add('originalCurrency', ChoiceType::class, [
                'label' => 'Devise',
                'choices' => [
                    'TND' => 'TND',
                    'USD' => 'USD',
                    'EUR' => 'EUR',
                    'GBP' => 'GBP',
                    'CHF' => 'CHF',
                    'CAD' => 'CAD',
                ],
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'La devise est obligatoire.'),
                    new Assert\Choice(choices: ['TND', 'USD', 'EUR', 'GBP', 'CHF', 'CAD'], message: 'Devise invalide.'),
                ],
            ])
            ->add('expenseDate', DateType::class, [
                'label' => 'Date de depense',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotNull(message: 'La date de depense est obligatoire.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Depense::class,
            'budget_choices' => [],
            'category_choices' => [],
            'selected_budget_id' => null,
        ]);
    }
}





