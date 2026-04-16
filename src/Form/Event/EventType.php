<?php

namespace App\Form\Event;

use App\Entity\Event\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Le titre est requis.'),
                    new Length(min: 5, max: 100),
                ],
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [
                    new NotBlank(message: 'La description est requise.'),
                    new Length(min: 10, max: 1000),
                ],
            ])
            ->add('startDate', DateTimeType::class, [
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(message: 'La date de debut est obligatoire.'),
                ],
            ])
            ->add('endDate', DateTimeType::class, [
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(message: 'La date de fin est obligatoire.'),
                ],
            ])
            ->add('location', TextType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Le lieu est obligatoire.'),
                ],
            ])
            ->add('gouvernorat', TextType::class, [
                'required' => false,
            ])
            ->add('imageUrl', TextType::class, [
                'required' => false,
            ])
            ->add('capacity', IntegerType::class, [
                'constraints' => [
                    new NotBlank(message: 'La capacite est obligatoire.'),
                    new Positive(message: 'La capacite doit etre positive.'),
                ],
            ])
            ->add('categoryId', IntegerType::class, [
                'constraints' => [
                    new NotBlank(message: 'La categorie est obligatoire.'),
                    new Positive(message: 'La categorie est invalide.'),
                ],
            ])
            ->add('isFree', CheckboxType::class, [
                'required' => false,
            ])
            ->add('ticketPrice', NumberType::class, [
                'constraints' => [
                    new PositiveOrZero(message: 'Le prix doit etre positif.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
            'csrf_protection' => false,
        ]);
    }
}
