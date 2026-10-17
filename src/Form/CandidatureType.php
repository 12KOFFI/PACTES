<?php

namespace App\Form;

use App\Config\EtablissementsActifsConfig;
use App\Entity\Candidature;
use App\Entity\Metier;
use App\Entity\Etablissement;
use App\Entity\SessionRecrutement;
use App\Service\Constant;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class CandidatureType extends AbstractType
{
    private $authorization;
    private $constant;
    private $entityManager;

    public function __construct(
        AuthorizationCheckerInterface $authorization,
        Constant $constant,
        EntityManagerInterface $entityManager
    ) {
        $this->authorization = $authorization;
        $this->constant = $constant;
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $evaluationOnly = $options['evaluation_only'];

        // Écouter l'événement PRE_SET_DATA pour configurer le formulaire avec les données existantes
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($evaluationOnly) {
            $form = $event->getForm();
            /** @var Candidature|null $data */
            $data = $event->getData();

            $etablissement = (!$evaluationOnly && $data) ? $data->getEtablissement() : null;

            $this->addFormFields($form, $etablissement, $data, $evaluationOnly);
        });

        // Écouter l'événement PRE_SUBMIT pour reconfigurer le formulaire avec les données soumises
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($evaluationOnly) {
            $form = $event->getForm();
            $data = $event->getData();
            $candidature = $form->getData();

            $etablissementId = (!$evaluationOnly && isset($data['etablissement'])) ? $data['etablissement'] : null;

            // Si l'établissement n'est pas transmis mais qu'un métier est présent
            if (!$etablissementId && isset($data['metier'])) {
                $metier = $this->entityManager->getRepository(Metier::class)->find($data['metier']);
                if ($metier && $metier->getEtablissementMetiers()->first()) {
                    $etablissement = $metier->getEtablissementMetiers()->first()->getEtablissement();
                } else {
                    $etablissement = null;
                }
            } else {
                $etablissement = $etablissementId ? $this->entityManager->getRepository(Etablissement::class)->find($etablissementId) : null;
            }

            $this->addFormFields($form, $etablissement, $candidature instanceof Candidature ? $candidature : null, $evaluationOnly);
        });
    }

    private function addFormFields(FormInterface $form, ?Etablissement $etablissement, ?Candidature $candidature = null, bool $evaluationOnly = false): void
    {
        if (!$evaluationOnly) {
            // Dépôt d'une nouvelle candidature : associations actives, et seulement si une session est ouverte
            $nouvelleCandidature = $candidature === null || $candidature->getId() === null;
            $sessionOuverte = !$nouvelleCandidature
                || $this->entityManager->getRepository(SessionRecrutement::class)->findActive() !== null;

            $form->add('etablissement', EntityType::class, [
                'class' => Etablissement::class,
                'query_builder' => function (EntityRepository $er) use ($nouvelleCandidature, $sessionOuverte) {
                    $qb = $er->createQueryBuilder('e')
                        ->leftJoin('e.localite', 'l')
                        ->addSelect(EtablissementsActifsConfig::getSortPriorityCaseWhen('e'))
                        ->where(EtablissementsActifsConfig::getDqlWhereClause('e'))
                        ->orderBy('sort_priority', 'ASC')
                        ->addOrderBy('e.nom', 'ASC');
                    if (!$sessionOuverte) {
                        $qb->andWhere('1 = 0');
                    } elseif ($nouvelleCandidature) {
                        $qb->innerJoin('e.etablissementMetiers', 'em_actif', 'WITH', 'em_actif.estActif = true')
                            ->distinct();
                    }
                    return $qb;
                },
                'choice_label' => 'nom',
                'choice_value' => 'id',
                'attr' => [
                    'data-metiers-target' => 'etablissement-select',
                    'class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition'
                ],
                'label' => 'Établissement',
                'placeholder' => '-- Choisissez un établissement --',
                'required' => true
            ]);

            // Configuration du métier (dynamique selon l'établissement)
            $metierOptions = [
                'class' => Metier::class,
                'choice_label' => 'nom',
                //'choice_value' => 'id',
                'label' => 'Métier',
                'required' => true,
                'attr' => [
                    'class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition'
                ]
            ];

            if ($etablissement) {
                // Si un établissement est sélectionné, charger les métiers associés
                $metierOptions['query_builder'] = function (EntityRepository $er) use ($etablissement, $nouvelleCandidature) {
                    $qb = $er->createQueryBuilder('m')
                        ->innerJoin('m.etablissementMetiers', 'em')
                        ->where('em.etablissement = :etablissement')
                        ->setParameter('etablissement', $etablissement)
                        ->orderBy('m.nom', 'ASC')
                        ->addOrderBy('m.nom', 'ASC');
                    if ($nouvelleCandidature) {
                        $qb->andWhere('em.estActif = true');
                    }
                    return $qb;
                };
                $metierOptions['placeholder'] = '-- Sélectionnez un métier --';
                $metierOptions['attr']['disabled'] = false;
            } else {
                // Sans établissement, liste vide et champ désactivé
                $metierOptions['choices'] = [];
                $metierOptions['placeholder'] = '-- Choisissez d\'abord un établissement --';
                $metierOptions['attr']['disabled'] = true;
            }

            $form->add('metier', EntityType::class, $metierOptions);

            // Champ caché pour stocker la valeur du niveau
            $form->add('niveau', HiddenType::class, [
                'data' => $this->getNiveauRequis($etablissement)
            ]);

            // Documents à télécharger
            // Documents à télécharger
            foreach ($this->constant->document_labels as $field => $config) {
                // Transformer l'attribut accept (.pdf, .jpg, .png) en types MIME valides pour Symfony
                $mimeTypes = $config['mimeTypes'] ?? [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                ];

                $form->add($field, FileType::class, [
                    'label' => $config['text'],
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'data-field' => $field,
                        'accept' => $config['accept'],
                        'class' => 'hidden file-input'
                    ],
                    'constraints' => [
                        new File([
                            'maxSize' => '2M',
                            'mimeTypes' => $mimeTypes,
                            'mimeTypesMessage' => 'Format ' . $config['formats'] . ' requis'
                        ])
                    ]
                ]);
            }
        } // fin if (!$evaluationOnly)

        // Champs pour les évaluateurs (ENT, ADMIN)
        if ($this->authorization->isGranted('ROLE_ENT') || $this->authorization->isGranted('ROLE_ADMIN')) {
            $form
                ->add('etustatut', ChoiceType::class, [
                    'choices' => [
                        'En attente' => null,
                        'Accepté' => 2,
                        'Rejeté' => 1
                    ],
                    'label' => 'Étude de dossier',
                    'required' => false,
                    'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
                ])
                ->add('etucom', TextType::class, [
                    'label' => 'Commentaire étude',
                    'required' => false,
                    'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
                ])
                ->add('entdate', DateType::class, [
                    'widget' => 'single_text',
                    'label' => 'Date entretien',
                    'required' => false,
                    'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
                ])
                ->add('entlieu', TextType::class, [
                    'label' => 'Lieu entretien',
                    'required' => false,
                    'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
                ])
                ->add('jury', IntegerType::class, [
                    'attr' => [
                        'min' => 1,
                        'max' => 20,
                        'class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition'
                    ],
                    'label' => 'Numéro du jury',
                    'required' => false
                ])
                ->add('creneauEntretien', IntegerType::class, [
                    'attr' => [
                        'min' => 1,
                        'class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition'
                    ],
                    'label' => 'Créneau',
                    'required' => false
                ]);

            // Configuration détaillée des notes selon la grille d'évaluation
            $notesConfig = [
                1 => ['label' => '1. Test de lecture', 'max' => 4],
                2 => ['label' => '2. Test d\'écriture', 'max' => 3],
                3 => ['label' => '3. Test de calcul', 'max' => 5],
                4 => ['label' => '4. Freins sociaux (logement, mobilité, santé, finance)', 'max' => 6],
                5 => ['label' => '5. Motivation et disponibilité', 'max' => 5],
                6 => ['label' => '6. Sens de l\'organisation', 'max' => 2],
                7 => ['label' => '7. Rapport à la hiérarchie et aux règles', 'max' => 2],
                8 => ['label' => '8. Sens des relations humaines et travail en équipe', 'max' => 3],
                9 => ['label' => '9. Gestion des conflits', 'max' => 2],
                10 => ['label' => '10. Connaissance du métier', 'max' => 2],
                11 => ['label' => '11. Capacité d\'analyse et de synthèse', 'max' => 2],
                12 => ['label' => '12. Première impression (politesse, tenue vestimentaire)', 'max' => 2],
                13 => ['label' => '13. Élocution fluide, clarté des propos', 'max' => 2],
            ];

            foreach ($notesConfig as $i => $config) {
                $form->add('note' . $i, NumberType::class, [
                    'label' => $config['label'],
                    'required' => false,
                    'scale' => 1, // Permet les décimales (ex: 2.5)
                    'attr' => [
                        'min' => 0,
                        'max' => $config['max'],
                        'step' => 0.25, // Pas de 0.25 pour plus de précision
                        'class' => 'w-full font-bold px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition note-input',
                        'data-max' => $config['max'] // Stocke la note max pour le JS
                    ]
                ]);
            }

            $form->add('entcom', TextType::class, [
                'label' => 'Commentaire entretien',
                'required' => false,
                'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
            ]);
        }

        // Champs Nom/Prénom du candidat (édition uniquement pour ENT/ADMIN, hors mode évaluation)
        if (
            !$evaluationOnly &&
            ($this->authorization->isGranted('ROLE_ENT') || $this->authorization->isGranted('ROLE_ADMIN')) &&
            $candidature !== null && $candidature->getId() !== null
        ) {
            $inputCls = 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition';
            $user = $candidature->getUser();
            $form->add('nom', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Nom',
                'data' => $user ? ($user->getNom() ?? '') : '',
                'attr' => ['class' => $inputCls]
            ]);
            $form->add('prenoms', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Prénom(s)',
                'data' => $user ? ($user->getPrenoms() ?? '') : '',
                'attr' => ['class' => $inputCls]
            ]);
        }

        // Champs pour le jury
        if (
            $this->authorization->isGranted('ROLE_JURY') ||
            $this->authorization->isGranted('ROLE_ADMIN') ||
            $this->authorization->isGranted('ROLE_ENT')
        ) {
            $form
                ->add('entstatut', ChoiceType::class, [
                    'choices' => [
                        'Non effectué / Absent' => null,
                        'Entretien effectué' => 1
                    ],
                    'label' => 'Présence à l\'entretien',
                    'required' => false,
                    'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
                ])
                ->add('decisionJury', ChoiceType::class, [
                    'choices' => [
                        'En attente' => null,
                        'Admissible' => 2,
                        'Non admissible' => 1
                    ],
                    'label' => 'Décision du jury',
                    'required' => false,
                    'attr' => [
                        'class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition'
                    ]
                ])
                ->add('visdate', DateType::class, [
                    'widget' => 'single_text',
                    'label' => 'Date visite',
                    'required' => false,
                    'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
                ])
                ->add('vislieu', TextType::class, [
                    'label' => 'Lieu visite',
                    'required' => false,
                    'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
                ])
                ->add('visstatut', ChoiceType::class, [
                    'choices' => [
                        'En attente' => null,
                        'Effectué' => 2,
                        'Non effectué' => 1
                    ],
                    'label' => 'Statut visite',
                    'required' => false,
                    'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
                ])
                ->add('viscom', TextType::class, [
                    'label' => 'Commentaire visite',
                    'required' => false,
                    'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
                ])
                ->add('resultat', ChoiceType::class, [
                    'choices' => [
                        'En attente' => null,
                        'Admis' => 2,
                        'Non admis' => 1
                    ],
                    'label' => 'Résultat final',
                    'required' => false,
                    'attr' => ['class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition']
                ]);

            if (!$evaluationOnly && $this->authorization->isGranted('ROLE_ADMIN')) {
                $form->add('cohorte', IntegerType::class, [
                    'label' => 'Cohorte',
                    'required' => false,
                    'attr' => [
                        'class' => 'w-full px-4 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition',
                        'min' => 1,
                        'placeholder' => 'Ex. : 1',
                    ],
                ]);
            }
        }
    }

    private function getNiveauRequis(?Etablissement $etablissement): string
    {
        if (!$etablissement) {
            return 'Sélectionnez d\'abord un établissement';
        }

        // Par défaut, on ne peut pas déterminer le niveau car il dépend du métier
        // Ce sera mis à jour dynamiquement via JavaScript
        return 'Sélectionnez un métier';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Candidature::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'candidature_item',
            'evaluation_only' => false,
        ]);
    }
}
