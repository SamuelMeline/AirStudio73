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
                    'Pole Dance Abo annuel classique (1 paiement)' => 'pole_annuel_classique',
                    'Pole Dance Abo annuel classique (3 paiements)' => 'pole_annuel_classique_3x',
                    'Pole Dance Abo annuel classique (4 paiements)' => 'pole_annuel_classique_4x',
                    'Pole Dance Abo annuel classique (10 paiements)' => 'pole_annuel_classique_10x',
                    'Pole Dance Abo annuel classique (12 paiements)' => 'pole_annuel_classique_12x',
                    'Pole Dance Abo annuel classique + activité (1 paiement)' => 'pole_annuel_classique_activite',
                    'Pole Dance Abo annuel classique + activité (3 paiements)' => 'pole_annuel_classique_activite_3x',
                    'Pole Dance Abo annuel classique + activité (4 paiements)' => 'pole_annuel_classique_activite_4x',
                    'Pole Dance Abo annuel classique + activité (10 paiements)' => 'pole_annuel_classique_activite_10x',
                    'Pole Dance Abo annuel classique + activité (12 paiements)' => 'pole_annuel_classique_activite_12x',
                    'Pole Dance Abo souple (1 paiement)' => 'pole_souple',
                    'Pole Dance Abo souple (2 paiements)' => 'pole_souple_2x',
                    'Pole Dance Cours classique et d\'essai (1 paiement)' => 'pole_cours_classique_et_essaie',
                    'Pole Dance Cours particulier (1 paiement)' => 'pole_cours_particulier',
                    'Souplesse ou Renfo Abo annuel classique (1 paiement)' => 'souplesse_annuel_classique',
                    'Souplesse ou Renfo Abo Souple' => 'souplesse_souple',
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
