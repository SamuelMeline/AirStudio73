<?php

namespace App\Form;

use App\Entity\Plan;
use App\Entity\Subscription;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class SubscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plan', EntityType::class, [
                'class' => Plan::class,
                'choice_label' => 'name',
                'label' => 'Forfait',
                'placeholder' => 'Sélectionnez un forfait',
                'expanded' => false,
                'multiple' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                            ->orderBy('p.name', 'ASC');
                },
            ])
            ->add('promoCode', TextType::class, [
                'label' => 'Code Promotionnel',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Subscription::class,
        ]);
    }
}
