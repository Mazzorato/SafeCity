<?php

namespace App\Form;

use App\Entity\Report;
use App\Entity\ReportCategory;
use App\Entity\EmergencyService;
use App\Enum\GravityLevelEnum;
use App\Repository\EmergencyServiceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Construit et valide le formulaire Symfony ReportType.
 */
final class ReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $categoryOptions = [
            'class' => ReportCategory::class,
            'choice_label' => 'name',
            'label' => 'form.report_category',
            'expanded' => true,
            'multiple' => false,
            'placeholder' => false,
            'choice_attr' => static fn (ReportCategory $category): array => [
                'data-category-icon' => $category->getIcon(),
            ],
            'constraints' => [
                new NotBlank(message: 'validation.report_category_required'),
            ],
        ];

        $builder
            ->add('category', EntityType::class, $categoryOptions)
            ->add('description', TextareaType::class, [
                'label' => 'form.report_description',
                'attr' => [
                    'placeholder' => 'form.report_description_placeholder',
                    'rows' => 4,
                ],
                'constraints' => [
                    new NotBlank(message: 'validation.report_description_required'),
                    new Length(
                        max: 3000,
                        maxMessage: 'validation.report_description_too_long'
                    ),
                ],
            ])
            ->add('gravityLevel', EnumType::class, [
                'class' => GravityLevelEnum::class,
                'label' => 'form.gravity',
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'choice_label' => static fn (GravityLevelEnum $gravityLevel): string => match ($gravityLevel) {
                    GravityLevelEnum::LOW => 'gravity.low',
                    GravityLevelEnum::MEDIUM => 'gravity.medium',
                    GravityLevelEnum::HIGH => 'gravity.high',
                },
                'constraints' => [
                    new NotBlank(message: 'validation.gravity_required'),
                ],
            ])
            ->add('address', HiddenType::class, [
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Length(max: 255, maxMessage: 'validation.address_too_long'),
                ],
            ])
            ->add('latitude', HiddenType::class, [
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Range(
                        min: -90,
                        max: 90,
                        notInRangeMessage: 'validation.latitude_range'
                    ),
                ],
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Range(
                        min: -180,
                        max: 180,
                        notInRangeMessage: 'validation.longitude_range'
                    ),
                ],
            ]);

        if (!$options['report_creation']) {
            $builder
                ->add('emergencyService', EntityType::class, [
                    'class' => EmergencyService::class,
                    'choice_label' => 'name',
                    'label' => 'form.destination_service',
                    'placeholder' => 'form.awaiting_assignment',
                    'required' => false,
                    'query_builder' => static fn (EmergencyServiceRepository $repository) => $repository
                        ->createQueryBuilder('service')
                        ->where('service.status = :status')
                        ->setParameter('status', 'active')
                        ->orderBy('service.name', 'ASC'),
                ]);

            return;
        }

        foreach (['photo1', 'photo2', 'photo3'] as $field) {
            $builder->add($field, FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'validation.invalid_image_format',
                    ),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Report::class,
            'report_creation' => false,
        ]);
        $resolver->setAllowedTypes('report_creation', 'bool');
    }
}
