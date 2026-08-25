<?php
namespace App\Form;

use App\Entity\City;
use App\Entity\User;
use App\Localization\SupportedLocale;
use App\Repository\CityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Construit et valide le formulaire Symfony RegistrationFormType.
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'form.first_name',
                'attr' => [
                    'autocomplete' => 'given-name',
                    'placeholder' => 'form.first_name',
                ],
                'constraints' => [
                    new NotBlank(message: 'validation.first_name_required'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'form.last_name',
                'attr' => [
                    'autocomplete' => 'family-name',
                    'placeholder' => 'form.last_name',
                ],
                'constraints' => [
                    new NotBlank(message: 'validation.last_name_required'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'attr' => [
                    'autocomplete' => 'email',
                    'placeholder' => 'exemple@email.com',
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
            ->add('interfaceLanguage', ChoiceType::class, [
                'mapped' => false,
                'label' => 'form.interface_language',
                'choices' => SupportedLocale::choices(),
                'data' => SupportedLocale::DEFAULT,
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'form.accept_terms',
                'constraints' => [
                    new IsTrue(message: 'validation.terms_required'),
                ],
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