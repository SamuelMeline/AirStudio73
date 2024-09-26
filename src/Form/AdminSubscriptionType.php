<?php

namespace App\Form;

use App\Entity\Plan;
use App\Entity\Subscription;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class AdminSubscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Sélection du type de forfait (recharge la page quand un type est sélectionné)
        $builder->add('type', ChoiceType::class, [
            'label' => 'Type de Forfait',
            'placeholder' => 'Sélectionnez un type',
            'choices' => $this->getPlanTypes($options['em']),
            'mapped' => false,
            'required' => true,
            'attr' => [
                'onchange' => 'this.form.submit()', // Rechargement de la page
            ],
        ]);

        // Ajout du champ plan sans soumettre le formulaire
        $builder->add('plan', EntityType::class, [
            'class' => Plan::class,
            'choice_label' => 'name',
            'label' => 'Forfait',
            'placeholder' => 'Sélectionnez un forfait',
            'required' => false,
            'choices' => [], // Par défaut vide
            'attr' => [
                // Ne soumet pas le formulaire
            ],
        ]);

        // Écouter l'événement PRE_SUBMIT pour ajouter dynamiquement les options du plan en fonction du type sélectionné
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
            }
        });
    }

    // Méthode pour récupérer les types de forfaits
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
