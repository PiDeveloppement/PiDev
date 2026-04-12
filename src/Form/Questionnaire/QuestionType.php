<?php

namespace App\Form\Questionnaire;

use App\Entity\Event\Event;
use App\Entity\Questionnaire\Question;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('texte', TextType::class, [
                'label' => 'Énoncé de la question',
                'required' => false,
            ])
            ->add('reponse', TextType::class, [
                'label' => 'Réponse correcte',
                'required' => false,
            ])
            ->add('points', IntegerType::class, [
                'label' => 'Nombre de points',
                'required' => false,
                'attr' => ['min' => 0],
            ])
            ->add('option1', TextType::class, ['required' => false])
            ->add('option2', TextType::class, ['required' => false])
            ->add('option3', TextType::class, ['required' => false])
            ->add('event', EntityType::class, [
                'class' => Event::class,
                'choice_label' => 'title', // Vérifie que ton entité Event a bien un getTitle()
                'placeholder' => 'Choisir un événement',
                'required' => true, 
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Question::class,
        ]);
    }
}