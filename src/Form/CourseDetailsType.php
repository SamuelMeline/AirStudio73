<?php

namespace App\Form;

use App\Entity\CourseDetails;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class CourseDetailsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du cours',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('benefits', TextareaType::class, [
                'required' => false,
                'label' => 'Bienfaits'
            ])
            ->add('photo', FileType::class, [
                'label' => 'Photo',
                'required' => false,
                'mapped' => false, // Indicate that this field is not associated with any entity property
                'attr' => ['class' => 'form-control-file']
            ])
            ->add('photobenefits', FileType::class, [
                'label' => 'Photo Bienfaits',
                'required' => false,
                'mapped' => false, // Indicate that this field is not associated with any entity property
                'attr' => ['class' => 'form-control-file']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CourseDetails::class,
        ]);
    }
}
