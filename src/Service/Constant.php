<?php

namespace App\Service;

class Constant
{
    public $boolean =  ['OUI' => true, 'NON' => false];

    public $diplome = [
        'AUCUN' => 'AUCUN',
        'CEPE' => 'CEPE',
        'CQP' => 'CQP',
        'CAP' => 'CAP',
        'BEP' => 'BEP',
        'BEPC' => 'BEPC',
        'BP' => 'BP',
        'BAC' => 'BAC',
        'BT' => 'BT',
        'DUT' => 'DUT',
        'BTS' => 'BTS',
        'DEUG' => 'DEUG',
        'LICENCE' => 'LICENCE',
        'MASTER' => 'MASTER',
        'DOCTORAT' => 'DOCTORAT'
    ];
    public $niveauetude = [
        'CM2' => 'CM2',
        '6eme' => '6eme',
        '5eme' => '5eme',
        '4eme' => '4eme',
        '3eme' => '3eme',
        'SECONDE' => 'SECONDE',
        'PREMIERE' => 'PREMIERE',
        'TERMINALE' => 'TERMINALE',
        '1ere ANNEE' => '1ere ANNEE',
        '2eme ANNEE' => '2eme ANNEE',
        'LICENCE' => 'LICENCE',
        'MASTER' => 'MASTER',
        'DOCTORAT' => 'DOCTORAT'
    ];

    public $typepiece = ['CNI' => 'CNI', 'ATTESTATION D\'IDENTITE' => 'ATTESTATION D\'IDENTITE', 'PASSEPORT' => 'PASSEPORT'];
    public $sexe = ['MASCULIN' => 'MASCULIN', 'FEMININ' => 'FEMININ'];
    public $situationmat = ['CELIBATAIRE' => 'CELIBATAIRE', 'UNION LIBRE' => 'UNION LIBRE', 'MARIE(E)' => 'MARIE(E)', 'VEUF(VE)' => 'VEUF(VE)'];

    /** Taille maximale par pièce justificative : 5 Mo (doit rester <= upload_max_filesize de PHP) */
    public const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

    public $document_labels = [
        'fpiece' => [
            'icon' => 'id-card',
            'text' => 'Copie de la carte nationale d\'identité (CNI), de l\'attestation d\'identité ou du passeport',
            'accept' => '.jpg,.jpeg,.png,.pdf',
            'formats' => 'JPG, PNG, PDF',
            'mimeTypes' => [
                'application/pdf',
                'image/jpeg',
                'image/pjpeg',
                'image/png'
            ],
            'required' => true
        ],
        'fniveau' => [
            'icon' => 'graduation-cap',
            'text' => 'Diplôme requis / relevé de notes / autre document prouvant l\'exercice d\'une activité professionnelle liée au métier',
            'accept' => '.jpg,.jpeg,.png,.pdf',
            'formats' => 'JPG, PNG, PDF',
            'mimeTypes' => [
                'application/pdf',
                'image/jpeg',
                'image/pjpeg',
                'image/png'
            ],
            'required' => true
        ],
        'fautre' => [
            'icon' => 'file-alt',
            'text' => 'Copie de la carte de Couverture Maladie Universelle (CMU)',
            'accept' => '.jpg,.jpeg,.png,.pdf',
            'formats' => 'JPG, PNG, PDF',
            'mimeTypes' => [
                'application/pdf',
                'image/jpeg',
                'image/pjpeg',
                'image/png'
            ],
            'required' => true
        ]
    ];
}