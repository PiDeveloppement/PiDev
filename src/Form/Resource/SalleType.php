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
                'required' => false,
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'Capacité',
                'required' => false,
            ])
            ->add('building', TextType::class, [
                'label' => 'Bâtiment',
                'required' => false,
            ])
            ->add('floor', IntegerType::class, [
                'label' => 'Étage',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'DISPONIBLE' => 'DISPONIBLE',
                    'OCCUPEE' => 'OCCUPEE',
                ],
                'label' => 'Statut',
                'required' => false,
            ])
            // Champ pour l'URL (Unsplash)
            ->add('imagePath', TextType::class, [
                'required' => false,
                'label' => 'URL Image',
            ])
            // Champ pour l'upload physique (non mappé à l'entité)
            ->add('imageUpload', FileType::class, [
                'label' => 'Importer une image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WEBP)',
                    ])
                ],
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