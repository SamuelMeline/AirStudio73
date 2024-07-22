<?php

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints\Range;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $remainingCourses = $options['remaining_courses'];

        $builder
            ->add('isRecurrent', CheckboxType::class, [
                'required' => false,
                'label' => 'Réservation récurrente',
                'attr' => ['class' => 'js-recurrent-checkbox'],
            ])
            ->add('numOccurrences', IntegerType::class, [
                'required' => false,
                'label' => 'Nombre de cours récurrents',
                'attr' => ['class' => 'js-num-occurrences', 'min' => 1, 'max' => $remainingCourses],
                'constraints' => [
                    new Range(['min' => 1, 'max' => $remainingCourses])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
            'remaining_courses' => 0,
        ]);
    }
}
