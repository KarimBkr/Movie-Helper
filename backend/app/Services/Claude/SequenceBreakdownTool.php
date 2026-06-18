<?php

namespace App\Services\Claude;

/**
 * Définition stricte de l'outil Claude `extract_sequence_breakdown` (L-04 / C-04).
 *
 * Source de vérité UNIQUE pour le contrat IA. CLAUDE.md (règle 3) impose :
 *   - tool_choice forcé sur cet outil,
 *   - strict: true,
 *   - input_schema COMPLET (jamais {}),
 *   - lecture uniquement de content[type=tool_use].input.
 *
 * Le schéma reflète exactement le « Format réponse Claude » de CLAUDE.md :
 * un objet `sequence` (champs directs), une liste `elements`, `flags`, `notes`.
 *
 * Tout est `required` + `additionalProperties: false` : Claude doit produire la
 * structure entière, on ne tolère aucun champ surnuméraire ni manquant.
 */
class SequenceBreakdownTool
{
    public const NAME = 'extract_sequence_breakdown';

    /**
     * Catégories autorisées pour un sequence_element (enum element_category, V1).
     * Les champs directs de la séquence (resume/decor/int_ext/jour_nuit/huitiemes)
     * ne sont JAMAIS des catégories d'élément (CLAUDE.md).
     */
    public const ELEMENT_CATEGORIES = [
        'personnage', 'accessoire', 'vehicule', 'arme', 'animal',
        'sfx_vfx', 'costume', 'continuite', 'flag', 'note',
    ];

    public const CONFIDENCE_LEVELS = ['high', 'medium', 'low'];

    public const INT_EXT_VALUES = ['INT', 'EXT', 'INT/EXT', 'UNKNOWN'];

    /**
     * La définition complète envoyée dans le tableau `tools` de l'API Messages.
     *
     * @return array<string,mixed>
     */
    public static function definition(): array
    {
        return [
            'name' => self::NAME,
            'description' => 'Extrait le pré-dépouillement vérifiable d\'UNE séquence de scénario : '
                .'champs directs de la séquence (résumé, décor, INT/EXT, jour/nuit, huitièmes) '
                .'et éléments à dépouiller (personnages, accessoires, véhicules, armes, animaux, '
                .'SFX/VFX, costumes, points de continuité). Chaque valeur DOIT être justifiée par '
                .'un source_text littéralement présent dans le texte de la séquence. '
                .'L\'IA propose, l\'assistant décide : ne jamais inventer un élément absent du texte.',
            'input_schema' => self::inputSchema(),
        ];
    }

    /**
     * input_schema strict (JSON Schema). `additionalProperties: false` partout,
     * tous les champs `required`.
     *
     * @return array<string,mixed>
     */
    public static function inputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['sequence', 'elements', 'flags', 'notes'],
            'properties' => [
                'sequence' => self::sequenceSchema(),
                'elements' => self::elementsSchema(),
                'flags' => self::stringListSchema(
                    'Anomalies de dépouillement à signaler à l\'assistant (incohérence, '
                    .'ambiguïté, information manquante dans le texte).'
                ),
                'notes' => self::stringListSchema(
                    'Notes libres de contexte utiles à la vérification humaine.'
                ),
            ],
        ];
    }

    /**
     * Champs directs de la séquence (sequences.*), chacun {value, source_text, confidence}.
     *
     * @return array<string,mixed>
     */
    private static function sequenceSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['scene_number', 'resume', 'decor', 'int_ext', 'jour_nuit', 'huitiemes'],
            'properties' => [
                'scene_number' => [
                    'type' => ['string', 'null'],
                    'description' => 'Numéro de scène tel qu\'écrit dans le scénario, ou null si absent.',
                ],
                'resume' => self::sourcedField(
                    ['type' => 'string'],
                    'Résumé synthétique de l\'action de la séquence (1 à 2 phrases).'
                ),
                'decor' => self::sourcedField(
                    ['type' => 'string'],
                    'Décor principal de la séquence (lieu).'
                ),
                'int_ext' => self::sourcedField(
                    ['type' => 'string', 'enum' => self::INT_EXT_VALUES],
                    'Intérieur / extérieur déduit de l\'en-tête de scène.'
                ),
                'jour_nuit' => self::sourcedField(
                    ['type' => 'string'],
                    'Moment de la journée (JOUR, NUIT, MATIN, SOIR, AUBE, CREPUSCULE…).'
                ),
                'huitiemes' => self::sourcedField(
                    ['type' => ['number', 'null']],
                    'Estimation de la longueur en huitièmes de page, ou null si indéterminable.'
                ),
            ],
        ];
    }

    /**
     * Liste des éléments à dépouiller. source_text OBLIGATOIRE et non vide
     * (règle 7 de CLAUDE.md ; rejet définitif côté Laravel si manquant).
     *
     * @return array<string,mixed>
     */
    private static function elementsSchema(): array
    {
        return [
            'type' => 'array',
            'description' => 'Éléments à dépouiller, un par entrée. Ne contient QUE des éléments '
                .'effectivement présents dans le texte de la séquence.',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['category', 'value', 'source_text', 'confidence', 'note'],
                'properties' => [
                    'category' => [
                        'type' => 'string',
                        'enum' => self::ELEMENT_CATEGORIES,
                        'description' => 'Catégorie de l\'élément (enum V1).',
                    ],
                    'value' => [
                        'type' => 'string',
                        'minLength' => 1,
                        'description' => 'Libellé de l\'élément (ex: « revolver », « Marc »).',
                    ],
                    'source_text' => [
                        'type' => 'string',
                        'minLength' => 1,
                        'description' => 'Extrait LITTÉRAL du texte de la séquence qui justifie '
                            .'l\'élément. Obligatoire et non vide.',
                    ],
                    'confidence' => [
                        'type' => 'string',
                        'enum' => self::CONFIDENCE_LEVELS,
                        'description' => 'Niveau de confiance de l\'extraction.',
                    ],
                    'note' => [
                        'type' => 'string',
                        'description' => 'Précision optionnelle pour l\'assistant (chaîne vide si rien).',
                    ],
                ],
            ],
        ];
    }

    /**
     * Champ « sourcé » {value, source_text, confidence} dont seul le type de value varie.
     *
     * @param  array<string,mixed>  $valueSchema
     * @return array<string,mixed>
     */
    private static function sourcedField(array $valueSchema, string $description): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value', 'source_text', 'confidence'],
            'description' => $description,
            'properties' => [
                'value' => $valueSchema,
                'source_text' => [
                    'type' => 'string',
                    'description' => 'Extrait du texte qui justifie la valeur (chaîne vide si déduit de l\'en-tête).',
                ],
                'confidence' => [
                    'type' => 'string',
                    'enum' => self::CONFIDENCE_LEVELS,
                ],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function stringListSchema(string $description): array
    {
        return [
            'type' => 'array',
            'description' => $description,
            'items' => ['type' => 'string'],
        ];
    }
}
