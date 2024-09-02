<?php

// src/Form/CourseType.php

namespace App\Form;

use App\Entity\Course;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('startTime', DateTimeType::class, [
                'widget' => 'single_text',
            ])
            ->add('endTime', DateTimeType::class, [
                'widget' => 'single_text',
            ])
            ->add('capacity', IntegerType::class)
            ->add('isRecurrent', CheckboxType::class, [
                'label'    => 'Cours récurrent',
                'required' => false,
            ])
            ->add('recurrenceDuration', ChoiceType::class, [
                'label'    => 'Durée de récurrence',
                'choices'  => [
                    '1 mois' => '1_month',
                    '3 mois' => '3_months',
                    '6 mois' => '6_months',
                    '1 an' => '1_year',
                    '2 ans' => '2_years',
                    '3 ans' => '3_years',
                ],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
}
