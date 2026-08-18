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

/**
 * Construit et valide le formulaire Symfony ReportFormType.
 */
class ReportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EntityType::class,[
                'class' => ReportCategory::class,
                'choice_label' => 'name',
                'label' => 'form.report_category',
                'constaints' => [
                new NotBlank (message: 'validation.report_category_required')],
            ]) 

            ->add('description', TextareaType::class, [
                'label' => 'form.report_description',
                'required' => false,
                'attr' => ['placeholder' => 'form.report_description_placeholder', 'rows' => 4],
            ])
            ->add('gravityLevel', EnumType::class, [
                'class' => GravityLevelEnum::class,
                'label' => 'form.gravity',
                'constraints' => [
                    new NotBlank(message: 'validation.gravity_required'),
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
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Report::class,
        ]);
    }
}


