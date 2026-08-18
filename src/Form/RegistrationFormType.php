<?php
namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\RepeatedType;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Repository\CityRepository;

use App\Entity\City;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Please enter your first name'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Please enter your last name'),
                ],
            ])
            ->add('email')
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'You should agree to our terms.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'validation.passwords_must_match',
                'first_options' => [
                    'label' => 'form.password',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'form.password_minimum',
                    ],
                ],
                'second_options' => [
                    'label' => 'form.password_confirm',
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'form.password_confirm',
                    ],
                ],
                'constraints' => [
                    new NotBlank(message: 'validation.password_required'),
                    new Length(
                        min: 8,
                        minMessage: 'validation.password_too_short',
                        max: 4096,
                    ),
                ],
            ])
            ->add('city', EntityType::class, [
                'class' => City::class,
                'choice_label' => static fn (City $city): string => sprintf('%s (%s)', $city->getName(), $city->getPostalCode()),
                'query_builder' => static fn (CityRepository $repository) => $repository
                    ->createQueryBuilder('city')
                    ->where('city.available = true')
                    ->orderBy('city.name', 'ASC'),
                'label' => 'form.city',
                'placeholder' => 'form.city_placeholder',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}