<?php

namespace App\Form;

use App\Entity\Plan;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class PlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du forfait',
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
            ->add('planCourses', CollectionType::class, [
                'entry_type' => PlanCourseType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__name__',
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Plan::class,
        ]);
    }
}
