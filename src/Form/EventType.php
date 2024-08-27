<?php

namespace App\Form;

use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
            ])
            ->add('startTime', DateTimeType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
            ])
            ->add('endTime', DateTimeType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu',
            ])
            ->add('photo', FileType::class, [
                'label' => 'Photo',
                'required' => false,
                'mapped' => false, // Indicate that this field is not associated with any entity property
                'attr' => ['class' => 'form-control-file']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
