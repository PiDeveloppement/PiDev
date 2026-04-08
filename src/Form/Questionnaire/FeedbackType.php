<?php

namespace App\Form\Questionnaire;

use App\Entity\Questionnaire\Feedback;
use App\Entity\Questionnaire\Question;
use App\Entity\User\UserModel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FeedbackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
       // src/Form/Questionnaire/FeedbackType.php

$builder
    // On lie la question via l'entité Question
      ->add('question', EntityType::class, [
    'class' => Question::class,
    // On utilise 'texte' car l'entité possède la méthode getTexte()
    'choice_label' => 'texte', 
    'label' => 'Question concernée',
    'attr' => ['class' => 'form-select']
])
            ->add('reponseDonnee', TextType::class, [
                'label' => 'Réponse du participant',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Option A']
            ])
            ->add('comments', TextareaType::class, [
                'label' => 'Commentaire / Avis',
                'required' => false,
                'attr' => [
                    'class' => 'form-control', 
                    'rows' => 3,
                    'placeholder' => 'Laissez un avis ici...'
                ]
            ])
            ->add('etoiles', IntegerType::class, [
                'label' => 'Note (0 à 5)',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 5
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Feedback::class,
        ]);
    }
}