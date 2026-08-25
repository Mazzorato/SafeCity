<?php

namespace App\Form;

use App\Entity\City;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Construit et valide le formulaire Symfony UserFormType.
 */
final class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'form.first_name',
                'constraints' => [
                    new NotBlank(message: 'validation.first_name_required'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'form.last_name',
                'constraints' => [
                    new NotBlank(message: 'validation.last_name_required'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'constraints' => [
                    new NotBlank(message: 'validation.email_required'),
                    new Email(message: 'validation.email_invalid'),
                ],
            ])
            ->add('city', EntityType::class, [
                'class' => City::class,
                'choice_label' => 'name',
                'choice_filter' => static fn (City $city): bool => $city->isAvailable(),
                'label' => 'form.city',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}