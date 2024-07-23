<?php

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $remainingCourses = $options['remaining_courses']; // Obtenez le solde des cours restants

        $builder
            ->add('isRecurrent', CheckboxType::class, [
                'required' => false,
                'label' => 'Réservation récurrente',
                'attr' => ['class' => 'js-recurrent-checkbox'],
            ])
            ->add('numOccurrences', IntegerType::class, [
                'required' => false,
                'label' => 'Nombre de cours récurrents',
                'attr' => [
                    'class' => 'js-num-occurrences',
                    'min' => 1,
                    'max' => $remainingCourses, // Définissez la limite maximale ici
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
            'remaining_courses' => 0, // Valeur par défaut
        ]);
    }
}
