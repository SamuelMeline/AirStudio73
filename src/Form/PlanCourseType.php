<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\PlanCourse;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class PlanCourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('course', EntityType::class, [
                'class' => Course::class,
                'choice_label' => 'name',
                'label' => 'Cours',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->groupBy('c.name');
                },
            ])
            ->add('credits', IntegerType::class, [
                'label' => 'Crédits',
            ])
            ->add('pricePerCredit', NumberType::class, [
                'label' => 'Prix du Crédit',
                'scale' => 2, // Permet d'accepter deux décimales
                'attr' => [
                    'step' => '0.01', // Permet la saisie des centimes
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanCourse::class,
        ]);
    }
}
