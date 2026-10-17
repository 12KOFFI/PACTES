<?php

namespace App\Form;

use App\Entity\Localite;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocaliteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'attr' => [
                    'class' => 'form-control text-uppercase',
                    'placeholder' => 'Ex: Abidjan'
                ],
                'label' => 'Nom'
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Région' => 'region',
                    'Département' => 'departement',
                    'Commune' => 'commune',
                    'Ville' => 'ville',
                    'Sous-préfecture' => 'sous-prefecture',
                    'Village' => 'village',
                ],
                'attr' => ['class' => 'form-select'],
                'label' => 'Type',
                'placeholder' => '-- CHOISIR LE TYPE --'
            ])
            ->add('parent', EntityType::class, [
                'class' => Localite::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('l')
                        ->orderBy('l.nom', 'ASC');
                },
                'choice_label' => 'nom',
                'attr' => ['class' => 'form-select'],
                'label' => 'Localité parente',
                'placeholder' => '-- AUCUN --',
                'required' => false,
                // Regrouper par type pour une meilleure organisation
                'group_by' => function($choice) {
                    return match($choice->getType()) {
                        'region' => 'Régions',
                        'departement' => 'Départements',
                        'commune' => 'Communes',
                        'ville' => 'Villes',
                        'sous-prefecture' => 'Sous-préfectures',
                        'village' => 'Villages',
                        default => 'Autres'
                    };
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Localite::class,
        ]);
    }
}