<?php

namespace App\Form;

use App\Entity\Plan;
use App\Entity\Course;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class PlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Get the courses from options
        $courses = $options['courses'];

        // Remove duplicate course names
        $uniqueCourses = [];
        foreach ($courses as $course) {
            $uniqueCourses[$course->getName()] = $course;
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du forfait',
            ])
            ->add('course', EntityType::class, [
                'class' => Course::class,
                'choices' => $uniqueCourses,
                'choice_label' => 'name',
                'label' => 'Cours',
                'placeholder' => 'Sélectionnez un cours',
            ])
            ->add('duration', ChoiceType::class, [
                'label' => 'Durée du forfait',
                'choices' => [
                    '3 Mois' => 'P3M',
                    '1 An' => 'P1Y',
                ],
                'placeholder' => 'Sélectionnez une durée',
            ])
            ->add('stripePriceId', TextType::class, [
                'label' => 'ID du prix Stripe',
            ])
            ->add('courses', IntegerType::class, [
                'label' => 'Nombre de cours',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Plan::class,
        ]);

        // Add courses as an option
        $resolver->setRequired(['courses']);
    }
}
