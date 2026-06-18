<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast pour les colonnes Postgres `text[]` (ex : sequences.flags).
 *
 * Le cast natif `array` de Laravel suppose du JSON ; or Postgres renvoie un
 * littéral tableau (`{a,b}`) et l'attend en écriture. On convertit donc :
 *   lecture  : `{a,b}`  → ['a','b']
 *   écriture : ['a','b'] → `{a,b}`
 *
 * @implements CastsAttributes<list<string>, list<string>>
 */
class PostgresTextArray implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '' || $value === '{}') {
            return [];
        }

        // Déjà décodé (ex : valeur fraîchement settée et non rechargée).
        if (is_array($value)) {
            return array_values($value);
        }

        $inner = trim((string) $value, '{}');

        if ($inner === '') {
            return [];
        }

        // Découpe en respectant les éléments entre guillemets (virgules incluses).
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|[^,]+/', $inner, $matches);

        return array_map(
            static fn (string $item): string => stripcslashes(trim($item, '"')),
            $matches[0],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $items = is_array($value) ? array_values($value) : [$value];

        if ($items === []) {
            return '{}';
        }

        $escaped = array_map(
            static fn (mixed $item): string => '"'.addcslashes((string) $item, '"\\').'"',
            $items,
        );

        return '{'.implode(',', $escaped).'}';
    }
}
