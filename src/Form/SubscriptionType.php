<?php

namespace App\Form;

use App\Entity\Plan;
use App\Entity\Subscription;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class SubscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Sélection du type de forfait
        $builder->add('type', ChoiceType::class, [
            'label' => 'Type de Forfait',
            'placeholder' => 'Sélectionnez un type',
            'choices' => $this->getPlanTypes($options['em']),
            'mapped' => false,
            'required' => true,
            'attr' => [
                'onchange' => 'this.form.submit()', // Soumettre automatiquement le formulaire
            ],
        ]);

        // Ajout du champ plan avec un placeholder vide par défaut
        $builder->add('plan', EntityType::class, [
            'class' => Plan::class,
            'choice_label' => 'name',
            'label' => 'Forfait',
            'placeholder' => 'Sélectionnez un forfait',
            'required' => false,
            'choices' => [], // Par défaut vide
        ]);

        // Écouter l'événement PRE_SUBMIT pour ajouter dynamiquement les options du plan
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $form = $event->getForm();
            $data = $event->getData();
            $type = $data['type'] ?? null;

            if ($type) {
                // Mise à jour du champ plan en fonction du type sélectionné
                $form->add('plan', EntityType::class, [
                    'class' => Plan::class,
                    'choice_label' => 'name',
                    'label' => 'Forfait',
                    'placeholder' => 'Sélectionnez un forfait',
                    'required' => true,
                    'query_builder' => function (EntityRepository $er) use ($type) {
                        return $er->createQueryBuilder('p')
                            ->where('p.type = :type')
                            ->setParameter('type', $type)
                            ->orderBy('p.name', 'ASC');
                    },
                ]);

                // Récupérer le plan sélectionné
                $planId = $data['plan'] ?? null;
                if ($planId) {
                    $em = $options['em'];
                    $plan = $em->getRepository(Plan::class)->find($planId);

                    // Ajustement des options de paiement en fonction du type d'abonnement
                    if ($plan) {
                        $subscriptionType = $plan->getSubscriptionType();

                        // Adapter le champ "paymentInstallments" selon le type de plan
                        switch ($subscriptionType) {
                            case 'unlimited':
                            case 'weekly':
                            case 'bi-weekly':
                                $form->add('paymentInstallments', ChoiceType::class, [
                                    'label' => 'Nombre de paiements',
                                    'choices' => [
                                        'Sélectionner un mode de paiement' => null,
                                        'Payer en une fois' => 1,
                                        'Payer en 3 fois' => 3,
                                        'Payer en 10 fois' => 10,
                                    ],
                                    'required' => true,
                                ]);
                                break;

                            case 'unit':
                            case 'weekly-renewable':
                                $form->add('paymentInstallments', ChoiceType::class, [
                                    'label' => 'Nombre de paiements',
                                    'choices' => [
                                        'Sélectionner un mode de paiement' => null,
                                        'Payer en une fois' => 1,
                                    ],
                                    'required' => true,
                                ]);
                                break;

                            case 'souple':
                                $form->add('paymentInstallments', ChoiceType::class, [
                                    'label' => 'Nombre de paiements',
                                    'choices' => [
                                        'Sélectionner un mode de paiement' => null,
                                        'Payer en une fois' => 1,
                                        'Payer en 2 fois' => 2,
                                    ],
                                    'required' => true,
                                ]);
                                break;
                        }
                    }
                }
            }
        });

        // Champ pour entrer un code promo
        $builder->add('promoCode', TextType::class, [
            'label' => 'Code Promotionnel',
            'required' => false,
        ]);
    }

    private function getPlanTypes($em): array
    {
        $planRepository = $em->getRepository(Plan::class);
        $types = $planRepository->createQueryBuilder('p')
            ->select('p.type')
            ->distinct(true)
            ->getQuery()
            ->getResult();

        $choices = [];
        foreach ($types as $type) {
            $choices[$type['type']] = $type['type'];
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Subscription::class,
            'em' => null,
        ]);
    }
}
