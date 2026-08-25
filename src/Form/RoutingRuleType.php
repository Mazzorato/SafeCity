<?php

namespace App\Form;

use App\Entity\EmergencyService;
use App\Entity\ReportCategory;
use App\Entity\RoutingRule;
use App\Enum\GravityLevelEnum;
use App\Repository\EmergencyServiceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Construit et valide le formulaire Symfony RoutingRuleType.
 */
final class RoutingRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('gravityLevel', EnumType::class, [
                'class' => GravityLevelEnum::class,
                'label' => 'form.gravity',
                // Les valeurs de l’énumération restent stables en base ; seul
                // leur libellé visible passe par le catalogue de l’utilisateur.
                'choice_label' => static fn (GravityLevelEnum $gravity): string => 'gravity.' . $gravity->value,
            ])
            ->add('category', EntityType::class, [
                'class' => ReportCategory::class,
                'choice_label' => 'name',
                'label' => 'admin.category',
                'placeholder' => 'admin.all_categories',
                'required' => false,
            ])
            ->add('emergencyService', EntityType::class, [
                'class' => EmergencyService::class,
                'choice_label' => 'name',
                'label' => 'form.destination_service',
                'query_builder' => static fn (EmergencyServiceRepository $repository) => $repository
                    ->createQueryBuilder('service')
                    ->where('service.status = :status')
                    ->setParameter('status', 'active')
                    ->orderBy('service.name', 'ASC'),
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'admin.priority',
                'help' => 'admin.priority_help',
                'constraints' => [
                    new Range(min: 0, max: 1000),
                ],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'admin.rule_enabled',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RoutingRule::class,
        ]);
    }
}