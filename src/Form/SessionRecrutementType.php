<?php

namespace App\Form;

use App\Entity\SessionRecrutement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SessionRecrutementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la session',
                'attr' => ['placeholder' => 'Ex. Session 2026 - Vague 1']
            ])
            ->add('annee', IntegerType::class, [
                'label' => 'Année',
                'attr' => ['min' => 2020, 'max' => 2100]
            ])
            ->add('numeroVague', IntegerType::class, [
                'label' => 'Numéro de vague',
                'attr' => ['min' => 1],
                'help' => 'Apparaît dans le numéro de candidature (ex: DK2601xxxx = vague 01). Ne pas réutiliser un numéro déjà attribué la même année.'
            ])
            ->add('dateOuverture', DateTimeType::class, [
                'label' => 'Ouverture des inscriptions',
                'widget' => 'single_text',
                'required' => false
            ])
            ->add('dateCloture', DateTimeType::class, [
                'label' => 'Clôture des inscriptions',
                'widget' => 'single_text',
                'required' => false
            ])
            ->add('dateReferenceAge', DateType::class, [
                'label' => "Date de référence pour l'âge",
                'widget' => 'single_text',
                'help' => "Les âges minimum et maximum sont calculés à cette date."
            ])
            ->add('ageMin', IntegerType::class, [
                'label' => 'Âge minimum (inclus)',
                'attr' => ['min' => 14, 'max' => 99]
            ])
            ->add('ageMax', IntegerType::class, [
                'label' => 'Âge maximum (inclus)',
                'attr' => ['min' => 14, 'max' => 99]
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => SessionRecrutement::STATUT_BROUILLON,
                    'Ouverte' => SessionRecrutement::STATUT_OUVERTE,
                    'Fermée' => SessionRecrutement::STATUT_FERMEE,
                    'Clôturée' => SessionRecrutement::STATUT_CLOTUREE,
                ]
            ])
            ->add('active', ChoiceType::class, [
                'label' => 'Session active',
                'choices' => ['Oui' => true, 'Non' => false],
                'expanded' => true
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 5]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SessionRecrutement::class]);
    }
}
