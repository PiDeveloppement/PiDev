<?php

namespace App\Form;

use App\Entity\Resource\Equipement; // Vérifie bien que le dossier "Resource" existe dans Entity
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class EquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Désignation',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Projecteur LED']
            ])
            ->add('equipementType', TextType::class, [ // Changé pour matcher le Twig
                'label' => 'Catégorie',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'choices'  => [
                    'Disponible' => 'DISPONIBLE',
                    'En Panne'   => 'EN_PANNE',
                ],
                'label' => 'Statut',
                'required' => false,
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité',
                'required' => false,
                'attr' => ['min' => 0]
            ])
            ->add('imagePath', FileType::class, [ // Changé pour matcher le Twig
                'label' => 'Image (Fichier)',
                'mapped' => false, 
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image JPG ou PNG valide',
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipement::class,
        ]);
    }
}