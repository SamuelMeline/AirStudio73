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

        // Ajouter le champ pour sélectionner le plan (forfait)
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $form = $event->getForm();
            $data = $event->getData();
            $type = $data['type'] ?? null;

            if ($type) {
                $form->add('plan', EntityType::class, [
                    'class' => Plan::class,
                    'choice_label' => 'name',
                    'label' => 'Forfait',
                    'placeholder' => 'Sélectionnez un forfait',
                    'required' => true,
                    'query_builder' => function (EntityRepository $er) use ($type) {
                        return $er->createQueryBuilder('p')
                            ->where('p.type = :type')
                            ->andWhere('p.endDate >= :currentDate')
                            ->setParameter('type', $type)
                            ->setParameter('currentDate', new \DateTime())
                            ->orderBy('p.name', 'ASC');
                    },
                ]);
            }

            // Ajuster dynamiquement les options de paiement en fonction du type de plan sélectionné
            if (isset($data['plan'])) {
                $planId = $data['plan'];
                $plan = $options['em']->getRepository(Plan::class)->find($planId);

                if ($plan) {
                    $paymentChoices = [];

                    switch ($plan->getType()) {
                        case 'unit':
                            $paymentChoices = [
                                'Payer en une fois' => 1,
                            ];
                            break;
                        case 'souple':
                            $paymentChoices = [
                                'Payer en une fois' => 1,
                                'Payer en 2 fois' => 2,
                            ];
                            break;
                        case 'weekly':
                        case 'bi-weekly':
                        case 'unlimited':
                            $paymentChoices = [
                                'Payer en une fois' => 1,
                                'Payer en 3 fois' => 3,
                                'Payer en 10 fois' => 10,
                            ];
                            break;
                    }

                    // Ajouter le champ pour choisir le nombre de paiements dynamiquement
                    $form->add('paymentInstallments', ChoiceType::class, [
                        'label' => 'Nombre de paiements',
                        'choices' => $paymentChoices,
                        'required' => true,
                        'attr' => ['class' => 'form-control'],
                    ]);
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
