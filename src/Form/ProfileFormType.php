<?php

namespace App\Form;

use App\Entity\Profile;
use App\Localization\SupportedLocale;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Construit et valide le formulaire Symfony ProfileFormType.
 */
final class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emergencyNotifications', CheckboxType::class, [
                'label' => 'form.emergency_notifications',
                'required' => false,
            ])
            ->add('transportNotifications', CheckboxType::class, [
                'label' => 'form.transport_notifications',
                'required' => false,
            ])
            ->add('eventNotifications', CheckboxType::class, [
                'label' => 'form.event_reminders',
                'required' => false,
            ])
            ->add('cameraAccess', CheckboxType::class, [
                'label' => 'form.camera_access',
                'required' => false,
            ])
            ->add('locationAccess', CheckboxType::class, [
                'label' => 'form.location_access',
                'required' => false,
            ])
            ->add('language', ChoiceType::class, [
                'label' => 'form.language',
                'choices' => SupportedLocale::choices(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Profile::class,
        ]);
    }
}