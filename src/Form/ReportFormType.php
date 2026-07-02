<?php

namespace App\Form;

use App\Entity\Report;
use App\Entity\ReportCategory;
use App\Enum\GravityLevelEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EntityType::class,[
                'class' => ReportCategory::class,
                'choice_label' => 'name',
                'label' => 'Type d\incident',
                'constaints' => [
                new NotBlank (message: 'Veuillez choisir une catégorie')],
            ]) 

            ->add('description', TextareaType::class, [
                'label' => 'Description de l\incident',
                'required' => false,
                'attr' => ['placeholder' => 'Décrivez l\incident en quelques mots...', 'rows' => 4],
            ])
            ->add('gravityLevel', EnumType::class, [
                'class' => GravityLevelEnum::class,
                'label' => 'Degré de gravité',
                'constraints' => [
                    new NotBlank(message: 'Veuillez choisir un niveau de gravité'),
                ]
            ])
            
            ->add('latitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('address', HiddenType::class, [
                'required' => false,
            ])
            ->add('audioUrl')
            ->add('audioLanguage', HiddenType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Report::class,
        ]);
    }
}
