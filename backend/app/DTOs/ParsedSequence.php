<?php

namespace App\DTOs;

/**
 * Une séquence extraite du FDX par le parser déterministe.
 *
 * Le parser ne renseigne que ce qu'il lit dans le XML (règle 6 de CLAUDE.md).
 * `resume` et `huitiemes` restent à l'IA → absents de ce DTO.
 *
 * `int_ext` / `day_night` valent 'UNKNOWN' si le heading est ambigu.
 * `parse_status` : 'parsed' (heading clair) | 'needs_review' (heading douteux / sans heading).
 */
readonly class ParsedSequence
{
    public function __construct(
        public int $displayOrder,
        public ?string $sceneNumber,
        public ?string $sceneHeading,
        public string $intExt,
        public ?string $decor,
        public ?string $subDecor,
        public string $dayNight,
        public string $rawText,
        public string $parseStatus,
    ) {}
}
