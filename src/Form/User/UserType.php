<?php

namespace App\Form\User;


use App\Entity\User\UserModel;
use App\Repository\Role\RoleRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function __construct(
        private RoleRepository $roleRepository
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isCreation = $options['is_creation'] ?? false;

        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Entrez le prénom',
                    'class' => 'form-control'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Entrez le nom',
                    'class' => 'form-control'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'exemple@email.com',
                    'class' => 'form-control'
                ]
            ])
            ->add('faculte', TextType::class, [
                'label' => 'Faculté',
                'attr' => [
                    'placeholder' => 'Entrez la faculté',
                    'class' => 'form-control'
                ]
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '+216 XX XXX XXX',
                    'class' => 'form-control'
                ]
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Biographie',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Description de l\'utilisateur...',
                    'class' => 'form-control'
                ]
            ])
            ->add('roleId', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => $this->getRoleChoices(),
                'attr' => ['class' => 'form-control']
            ]);

        // Champ mot de passe uniquement pour la création ou si explicitement demandé
        if ($isCreation) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['class' => 'form-control']
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['class' => 'form-control']
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => [
                    new NotBlank(['message' => 'Le mot de passe est requis']),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit faire au moins {{ limit }} caractères',
                    ]),
                ],
            ]);
        } else {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Nouveau mot de passe (laisser vide pour ne pas changer)',
                    'attr' => ['class' => 'form-control'],
                    'required' => false
                ],
                'second_options' => [
                    'label' => 'Confirmer le nouveau mot de passe',
                    'attr' => ['class' => 'form-control'],
                    'required' => false
                ],
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit faire au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ],
            ]);
        }
    }

    private function getRoleChoices(): array
    {
        $roles = $this->roleRepository->findAll();
        $choices = [];
        foreach ($roles as $role) {
            $choices[$role->getRoleName()] = $role->getId();
        }
        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserModel::class,
            'is_creation' => false,
        ]);
    }
}