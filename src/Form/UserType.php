<?php

namespace App\Form;

use App\Entity\User;
use App\Service\Constant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    private $constant;

    public function __construct(Constant $constant)
    {
        $this->constant = $constant;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        $isRegister = $options['is_register'] ?? false;
        $isAdmin = $options['is_admin'] ?? false;

        $required = !$isEdit;

        if ($isRegister || $isAdmin) {
            $builder->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => [
                    'placeholder' => 'exemple@email.com',
                    'autocomplete' => 'email'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est requis'])
                ]
            ]);
        } elseif ($isEdit) {
            $builder->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => [
                    'readonly' => true,
                    'class' => 'bg-slate-100 cursor-not-allowed'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est requis'])
                ]
            ]);
        }

        if ($isAdmin) {
            $builder->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'mapped' => false,
                'choices' => [
                    'Agent DAIP' => 'ROLE_AGENT',
                    'Agent établissement' => 'ROLE_ETABLISSEMENT',
                    'Jury' => 'ROLE_JURY',
                    'Administrateur' => 'ROLE_ADMIN',
                    'Candidat' => 'ROLE_CANDIDAT',
                ],
                'data' => $options['user_role'] ?? 'ROLE_CANDIDAT',
            ]);
        }

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => $required,
                'attr' => ['placeholder' => 'Votre nom'],
                'constraints' => $required ? [new NotBlank(['message' => 'Le nom est requis'])] : []
            ])
            ->add('prenoms', TextType::class, [
                'label' => 'Prénoms',
                'required' => $required,
                'attr' => ['placeholder' => 'Vos prénoms'],
                'constraints' => $required ? [new NotBlank(['message' => 'Les prénoms sont requis'])] : []
            ])
            ->add('nomjeunefille', TextType::class, [
                'label' => 'Nom de jeune fille',
                'required' => false,
                'attr' => ['placeholder' => 'Si applicable']
            ])
            ->add('sexe', ChoiceType::class, [
                'choices' => $this->constant->sexe,
                'label' => 'Sexe',
                'required' => $required,
                'placeholder' => '-- Sélectionnez --'
            ])
            ->add('datenaissance', BirthdayType::class, [
                'widget' => 'single_text',
                'label' => 'Date de naissance',
                'required' => $required,
                'attr' => ['max' => date('Y-m-d')]
            ])
            ->add('lieunaissance', TextType::class, [
                'label' => 'Lieu de naissance',
                'required' => $required,
                'attr' => ['placeholder' => 'Ville de naissance']
            ])
            ->add('residence', TextType::class, [
                'label' => 'Lieu de résidence',
                'required' => $required,
                'attr' => ['placeholder' => 'Votre ville de résidence']
            ])
            ->add('situationmat', ChoiceType::class, [
                'choices' => $this->constant->situationmat,
                'label' => 'Situation matrimoniale',
                'required' => false,
                'placeholder' => '-- Sélectionnez --'
            ])
            ->add('contact', TextType::class, [
                'label' => 'Contact',
                'required' => $required,
                'attr' => [
                    'placeholder' => 'Ex: 0708091011',
                    'pattern' => '[0-9]{10}',
                    'maxlength' => '10'
                ]
            ])
            ->add('nationalite', CountryType::class, [
                'label' => 'Nationalité',
                'choice_translation_locale' => 'fr',
                'data' => ($builder->getData() && $builder->getData()->getNationalite()) ? $builder->getData()->getNationalite() : 'CI',
                'required' => $required,
                'attr' => ['class' => 'select2']
            ])
            ->add('nompere', TextType::class, [
                'label' => 'Nom du père',
                'required' => false,
                'attr' => ['placeholder' => 'Nom et prénoms du père']
            ])
            ->add('nommere', TextType::class, [
                'label' => 'Nom de la mère',
                'required' => false,
                'attr' => ['placeholder' => 'Nom et prénom de la mère']
            ]);

        if ($isRegister || ($isAdmin && !$isEdit)) {
            $builder->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'required' => true,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['placeholder' => '••••••••'],
                    'constraints' => [
                        new NotBlank(['message' => 'Le mot de passe est requis']),
                        new Length(['min' => 6, 'minMessage' => 'Le mot de passe doit contenir au moins 6 caractères'])
                    ]
                ],
                'second_options' => [
                    'label' => 'Confirmation du mot de passe',
                    'attr' => ['placeholder' => '••••••••']
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'is_admin' => false,
            'is_register' => false,
            'user_role' => null
        ]);
    }
}
