<?php

namespace App\Form;

use App\Entity\Etablissement;
use App\Entity\EtablissementMetier;
use App\Entity\Metier;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtablissementMetierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('etablissement', EntityType::class, [
                'class' => Etablissement::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->orderBy('e.nom', 'ASC');
                },
                'choice_label' => 'nom',
                'attr' => ['class' => 'form-select'],
                'label' => 'Établissement de formation',
                'placeholder' => '-- CHOISIR --'
            ])
            ->add('metier', EntityType::class, [
                'class' => Metier::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('m')
                        ->orderBy('m.nom', 'ASC');
                },
                'choice_label' => 'nom',
                'attr' => ['class' => 'form-select'],
                'label' => 'Métier',
                'placeholder' => '-- CHOISIR --'
            ])
            ->add('nbrplace', IntegerType::class, [
                'attr' => ['class' => 'form-control', 'min' => 0],
                'label' => 'Nombre de places',
                'required' => false
            ])
            ->add('dureeFormation', ChoiceType::class, [
                'attr' => ['class' => 'form-select'],
                'choices' => [
                    '3 mois' => '3 mois',
                    '4 mois' => '4 mois',
                    '6 mois' => '6 mois',
                     '8 mois' => '8 mois',
                    '12 mois' => '12 mois',
                    '24 mois' => '24 mois'
                ],
                'label' => 'Durée de formation',
                'required' => false
            ])
            ->add('niveauRequis', ChoiceType::class, [
                'attr' => ['class' => 'form-select'],
                'choices' => [
                    'CEP' => 'CEP',
                    '5ème' => '5ème',
                    '3ème' => '3ème',
                    'BEPC' => 'BEPC',
                    'Terminale' => 'Terminale',
                    'BAC' => 'BAC'
                ],
                'label' => 'Niveau requis',
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EtablissementMetier::class,
        ]);
    }
}
