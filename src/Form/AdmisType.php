<?php

namespace App\Form;

use App\Entity\Admis;
use App\Entity\Candidature;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class AdmisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('candidature', EntityType::class, [
                'class' => Candidature::class,
                'choice_label' => function (Candidature $candidature) {
                    $user = $candidature->getUser();
                    $nom = $user ? $user->getNom() . ' ' . $user->getPrenoms() : 'N/A';
                    $numero = $candidature->getNumero();
                    $metier = $candidature->getMetier()?->getNom() ?? 'N/A';
                    return sprintf('%s - %s - %s', $numero, $nom, $metier);
                },
                'query_builder' => function (EntityRepository $er) use ($options) {
                    $admis = $options['data'] ?? null;
                    $qb = $er->createQueryBuilder('c')
                        ->where('c.resultat = 2')
                        ->leftJoin('c.admis', 'a');

                    if ($admis && $admis->getId() && $admis->getCandidature()) {
                        $qb->andWhere('a.id IS NULL OR c.id = :current_id')
                           ->setParameter('current_id', $admis->getCandidature()->getId());
                    } else {
                        $qb->andWhere('a.id IS NULL');
                    }

                    return $qb->orderBy('c.numero', 'ASC');
                },
                'placeholder' => 'Sélectionnez une candidature admise',
                'required' => true,
                'disabled' => ($options['data'] && $options['data']->getId() !== null),
            ])
            ->add('ficheEngagementFile', FileType::class, [
                'label' => 'Fiche d\'engagement signée (PDF/Image)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => '.pdf,.jpg,.jpeg,.png',
                    'class' => 'form-control'
                ],
                'help' => 'Veuillez uploader la fiche d\'engagement signée par le candidat'
            ])
            ->add('confirme', CheckboxType::class, [
                'label' => 'Je confirme avoir vérifié les informations du candidat et la fiche d\'engagement signée',
                'required' => true,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('entreprisePartenaire', EntityType::class, [
                //'class' => Prospection::class,
                'choice_label' => 'nomStructure',
                'label' => 'Entreprise Partenaire (pour le contrat)',
                'required' => false,
                'placeholder' => 'Sélectionnez une entreprise',
                'attr' => ['class' => 'form-control']
            ])
            ->add('contratFile', FileType::class, [
                'label' => 'Fiche du contrat d\'apprentissage (PDF)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/x-pdf',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un document PDF valide',
                    ])
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('livretFile', FileType::class, [
                'label' => 'Livret Apprenti (PDF/Image)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => '.pdf,.jpg,.jpeg,.png',
                    'class' => 'form-control'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Admis::class,
        ]);
    }
}