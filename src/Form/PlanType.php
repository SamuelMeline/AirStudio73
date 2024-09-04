<?php

namespace App\Form;

use App\Entity\Plan;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class PlanType extends AbstractType
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Récupération des types existants dans la base de données
        $types = $this->em->getRepository(Plan::class)->createQueryBuilder('p')
            ->select('p.type')
            ->distinct()
            ->getQuery()
            ->getResult();

        $typeChoices = [];
        foreach ($types as $type) {
            $typeChoices[$type['type']] = $type['type'];
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du forfait',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de Forfait',
                'choices' => $typeChoices,
                'placeholder' => 'Sélectionnez un type de forfait',
                'required' => false, // Permet la création d'un nouveau type s'il n'existe pas encore
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('maxPayments', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'choices' => [
                    'Paiement Comptant' => 1,
                    'Paiement en 2 fois' => 2,
                    'Paiement en 3 fois' => 3,
                    'Paiement en 10 fois' => 10,
                ],
                'placeholder' => 'Sélectionnez un mode de paiement',
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
