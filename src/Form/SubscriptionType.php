<?php

namespace App\Form;

use App\Entity\Subscription;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class SubscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('forfait', ChoiceType::class, [
                'label' => 'Forfait',
                'choices' => [
                    'Abo annuel classique (1 paiement)' => 'annuel_classique',
                    'Abo annuel classique (3 paiements)' => 'annuel_classique_3x',
                    'Abo annuel classique (4 paiements)' => 'annuel_classique_4x',
                    'Abo annuel classique (10 paiements)' => 'annuel_classique_10x',
                    'Abo annuel classique (12 paiements)' => 'annuel_classique_12x',
                    'Abo annuel classique + activité (1 paiement)' => 'annuel_classique_activite',
                    'Abo annuel classique + activité (3 paiements)' => 'annuel_classique_activite_3x',
                    'Abo annuel classique + activité (4 paiements)' => 'annuel_classique_activite_4x',
                    'Abo annuel classique + activité (10 paiements)' => 'annuel_classique_activite_10x',
                    'Abo annuel classique + activité (12 paiements)' => 'annuel_classique_activite_12x',
                    'Abo souple (1 paiement)' => 'souple',
                    'Abo souple (2 paiements)' => 'souple_2x',
                    'Cours classique et d\'essai (1 paiement)' => 'cours_classique_et_essaie',
                    'Cours particulier (1 paiement)' => 'cours_particulier',
                ],
                'placeholder' => 'Sélectionnez un forfait',
                'expanded' => false,
                'multiple' => false,
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
