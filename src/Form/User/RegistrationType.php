<?php

namespace App\Form\User;

use App\Entity\User\UserModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\HiddenType; 
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['placeholder' => 'Votre prénom', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est requis']),
                    new Length(['min' => 2, 'max' => 45])
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Votre nom', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est requis']),
                    new Length(['min' => 2, 'max' => 45])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['placeholder' => 'exemple@email.com', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est requis']),
                    new Email(['message' => 'Email invalide'])
                ]
            ])
            ->add('faculte', TextType::class, [
                'label' => 'Faculté',
                'attr' => ['placeholder' => 'Votre faculté', 'class' => 'form-control'],
                'constraints' => [new NotBlank(['message' => 'La faculté est requise'])]
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => '+216 XX XXX XXX', 'class' => 'form-control']
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['placeholder' => 'Minimum 6 caractères', 'class' => 'form-control']
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['placeholder' => 'Confirmez', 'class' => 'form-control']
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.'
            ])
          ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserModel::class,
            'csrf_protection' => true,
            'csrf_token_id' => 'registration'
        ]);
    }
}