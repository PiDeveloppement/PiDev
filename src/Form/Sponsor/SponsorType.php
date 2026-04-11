<?php

namespace App\Form\Sponsor;

use App\Entity\Sponsor\Sponsor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints as Assert;

class SponsorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fixedEmail = $options['fixed_email'];
        $fixedEventId = $options['fixed_event_id'];
        $eventChoices = $options['event_choices'];

        $emailFieldOptions = [
            'label' => 'Email de contact',
            'disabled' => false,
            'invalid_message' => 'Veuillez saisir un email valide.',
            'attr' => [
                'placeholder' => 'ex: sponsor@entreprise.com',
                'class' => 'form-control',
            ],
        ];

        if ($fixedEmail !== null && trim((string) $fixedEmail) !== '') {
            $emailFieldOptions['data'] = $fixedEmail;
            $emailFieldOptions['disabled'] = true;
        } else {
            $emailFieldOptions['required'] = false;
            $emailFieldOptions['empty_data'] = '';
        }

        $eventFieldOptions = [
            'label' => 'Evenement',
            'choices' => $eventChoices,
            'placeholder' => 'Selectionner un evenement',
            'disabled' => false,
            'invalid_message' => 'Veuillez selectionner un evenement valide.',
            'attr' => ['class' => 'form-control'],
        ];

        if (is_int($fixedEventId) && $fixedEventId > 0) {
            foreach ($eventChoices as $label => $value) {
                if ((int) $value === $fixedEventId) {
                    $eventFieldOptions['choices'] = [$label => $fixedEventId];
                    $eventFieldOptions['data'] = $fixedEventId;
                    break;
                }
            }
            $eventFieldOptions['disabled'] = true;
        }

        $builder
            ->add('eventId', ChoiceType::class, $eventFieldOptions)
            ->add('companyName', TextType::class, [
                'label' => 'Entreprise',
                'required' => false,
                'empty_data' => '',
                'invalid_message' => 'Veuillez saisir un nom d entreprise valide.',
                'attr' => [
                    'placeholder' => 'Nom entreprise',
                    'class' => 'form-control',
                ],
            ])
            ->add('contactEmail', EmailType::class, $emailFieldOptions)
            ->add('contributionName', NumberType::class, [
                'label' => 'Contribution (TND)',
                'scale' => 2,
                'html5' => false,
                'required' => false,
                'empty_data' => '',
                'invalid_message' => 'Veuillez saisir un montant valide.',
                'attr' => [
                    'step' => '0.01',
                    'min' => '0',
                    'placeholder' => 'ex: 1500.00',
                    'class' => 'form-control',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('industry', TextType::class, [
                'label' => 'Secteur',
                'required' => false,
                'invalid_message' => 'Veuillez saisir un secteur valide.',
                'attr' => [
                    'placeholder' => 'ex: Technologie',
                    'class' => 'form-control',
                    'pattern' => '[A-Za-zÀ-ÿ\\s\\-&,]+',
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Telephone',
                'required' => false,
                'invalid_message' => 'Veuillez saisir un numero de telephone valide.',
                'attr' => [
                    'placeholder' => 'ex: 21612345678',
                    'class' => 'form-control',
                    'maxlength' => '11',
                    'inputmode' => 'numeric',
                    'pattern' => '216[0-9]{8}',
                ],
            ])
            ->add('taxId', TextType::class, [
                'label' => 'N Fiscal',
                'required' => false,
                'invalid_message' => 'Veuillez saisir un numero fiscal valide.',
                'attr' => [
                    'placeholder' => 'ex: 1234567A',
                    'class' => 'form-control',
                    'maxlength' => '8',
                    'style' => 'text-transform: uppercase;',
                    'pattern' => '[0-9]{7}[A-Z]',
                ],
            ])
            ->add('logoUrl', TextType::class, [
                'label' => 'URL Logo',
                'required' => false,
                'invalid_message' => 'Veuillez saisir une URL de logo valide.',
                'attr' => [
                    'placeholder' => 'https://...',
                    'class' => 'form-control',
                ],
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Fichier logo (Image)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*',
                ],
                'constraints' => [
                    new Image(
                        maxSize: '4M',
                        maxSizeMessage: 'Le logo ne doit pas depasser 4 MB.',
                        mimeTypesMessage: 'Veuillez choisir une image valide (JPG, PNG, WEBP, GIF).'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sponsor::class,
            'fixed_email' => null,
            'fixed_event_id' => null,
            'event_choices' => [],
        ]);
    }
}
