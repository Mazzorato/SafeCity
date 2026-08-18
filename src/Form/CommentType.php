<?php

namespace App\Form;

use App\Entity\Comment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Construit et valide le formulaire Symfony CommentType.
 */
final class CommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'form.add_comment',
                'attr' => [
                    'placeholder' => 'form.add_comment_placeholder',
                    'rows' => 2,
                ],
                'constraints' => [
                    new NotBlank(message: 'validation.comment_required'),
                    new Length(
                        max: 1000,
                        maxMessage: 'validation.comment_too_long'
                    ),
                ],
            ])
            // Le fichier reste hors de l’entité Comment : seule la Photo créée
            // après validation est persistée par le contrôleur.
            ->add('photo', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'form.add_photo',
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Comment::class,
        ]);
    }
}
