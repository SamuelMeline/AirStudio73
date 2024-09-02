<?php

namespace App\Form;

use App\Entity\PlanCourse;
use App\Entity\Course;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanCourse::class,
        ]);
    }
}
