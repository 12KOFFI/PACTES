<?php
// src/Form/EtablissementType.php

namespace App\Form;

use App\Entity\Etablissement;
use App\Entity\Localite;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtablissementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'attr' => [
                    'class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-uppercase',
                    'placeholder' => 'Ex: CFPT Alépé'
                ],
                'label' => 'Nom court'
            ])
            ->add('definition', TextType::class, [
                'attr' => [
                    'class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition',
                    'placeholder' => 'Ex: Collège de Formation Professionnelle et Technique d\'Alépé'
                ],
                'label' => 'Nom complet',
                'required' => false
            ])
            // Champ Localité (filtré en fonction de la DR sélectionnée)
            ->add('localite', EntityType::class, [
                'class' => Localite::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('l')
                        ->orderBy('l.nom', 'ASC');
                },
                'choice_label' => 'nom',
                'attr' => [
                    'class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition',
                    'id' => 'etablissement_localite'
                ],
                'label' => 'Localité',
                'placeholder' => '-- SÉLECTIONNER D\'ABORD UNE DIRECTION RÉGIONALE --',
                'required' => false,
                'mapped' => true
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Etablissement::class,
        ]);
    }
}