<?php

namespace App\Form;

use App\Entity\Profile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emergencyNotifications', CheckboxType::class,[
                'label' => 'Notification d\'urgence',
                'required' => false,
            ])
            ->add('weatherNotifications', CheckboxType::class,[
                'label' => 'Alerte météo',
                'required' => false,
            ])
            ->add('transportNotifications', CheckboxType::class,[
                'label' => 'Perturbations transport',
                'required' => false,
            ])
            ->add('eventNotifications', CheckboxType::class,[
                'label' => 'Rappel d\'evenement',
                'required' => false,
            ])
            ->add('microphoneAccess', CheckboxType::class,[
                'label' => 'Accès microphone',
                'required' => false,
            ])
            ->add('cameraAccess', CheckboxType::class,[
                'label' => 'Accès caméra',
                'required' => false,
            ])
            ->add('locationAccess', CheckboxType::class,[
                'label' => 'Accès géolocalisation',
                'required' => false,
            ])
            ->add('language', ChoiceType::class,[
                'label' => 'Langue',
                'choices' => [
                    'Français' => 'fr',
                    'English' => 'en',
                    'Español' => 'es',
                    'Português' => 'pt',
                    'Italiano' => 'it',
                    'Deutsch' => 'de',
                    '日本語' => 'ja',
                    'العربية' => 'ar',
                    'Русский' => 'ru',
                    'Türkçe' => 'tr',
                    'Polski' => 'pl',
                    'Nederlands' => 'nl',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Profile::class,
        ]);
    }
}
