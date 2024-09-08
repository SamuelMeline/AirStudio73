<?php

namespace App\Form;

use App\Entity\Plan;
use App\Entity\Subscription;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class SubscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Ajout du champ type avec un placeholder
        $builder->add('type', ChoiceType::class, [
            'label' => 'Type de Forfait',
            'placeholder' => 'Sélectionnez un type', // Ajoute un placeholder
            'choices' => $this->getPlanTypes($options['em']),
            'mapped' => false, // Ce champ n'est pas lié à l'entité Subscription
            'required' => true,
            'attr' => [
                'onchange' => 'this.form.submit()', // Soumettre automatiquement le formulaire
            ],
            'choice_attr' => function ($choice, $key, $value) {
                return $key === 'Sélectionnez un type' ? ['disabled' => true, 'style' => 'display:none;'] : [];
            },
        ]);

        // Utiliser PRE_SUBMIT pour capturer la soumission du formulaire avec le type sélectionné
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
                    'expanded' => false,
                    'multiple' => false,
                    'required' => true,
                    'query_builder' => function (EntityRepository $er) use ($type) {
                        return $er->createQueryBuilder('p')
                            ->where('p.type = :type')
                            ->andWhere('p.name LIKE :name')
                            ->andWhere('p.endDate >= :currentDate')
                            ->setParameter('type', $type)
                            ->setParameter('name', 'Formule Découverte%')
                            ->setParameter('currentDate', new \DateTime())
                            ->orderBy('p.name', 'ASC');
                    },
                ]);
            }
        });

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
            'em' => null, // Ceci permet de passer l'EntityManager à travers les options
        ]);
    }
}
