<?php

namespace App\Form\Resource;

use App\Entity\Resource\Salle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class SalleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la salle',
                'required' => true,
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'Capacité',
                'required' => true,
                'attr' => [
                    'min' => 1,
                    'max' => 1000
                ]
            ])
            ->add('building', TextType::class, [
                'label' => 'Bâtiment',
                'required' => true,
            ])
            ->add('floor', IntegerType::class, [
                'label' => 'Étage',
                'required' => true,
                'attr' => [
                    'min' => 0,
                    'max' => 50
                ]
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'DISPONIBLE' => 'DISPONIBLE',
                    'OCCUPEE' => 'OCCUPEE',
                ],
                'label' => 'Statut',
                'required' => true,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image de la salle',
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Salle::class,
        ]);
    }
}