# FDX — Notes de structure (parser déterministe)

> Référence pour `FdxParserService` / `FdxXmlReader`. Le parser est **déterministe** :
> il extrait ce qui est présent dans le XML, il n'invente rien. L'IA enrichit ensuite,
> elle ne compense pas un mauvais parsing (règle 6 de CLAUDE.md).

Fixture de travail partagée : [`backend/storage/app/testing/fdx/sample.fdx.example`](../backend/storage/app/testing/fdx/sample.fdx.example).
Les vrais scénarios `.fdx` restent locaux (ignorés par git) ; **≥5 exemples anonymisés à fournir** (voir bas de page).

---

## 1. Format global

Un `.fdx` Final Draft est un fichier **XML UTF-8**. Racine `<FinalDraft>` :

```xml
<FinalDraft DocumentType="Script" Template="..." Version="5">
  <Content>
    <Paragraph Type="...">...</Paragraph>
    ...
  </Content>
</FinalDraft>
```

- `<Content>` contient la **liste ordonnée** des `<Paragraph>`. L'ordre du document = l'ordre de lecture.
- D'autres sections existent (`<TitlePage>`, `<SmartType>`, `<Macros>`, `<Revisions>`…) — **ignorées en V1**.
- Le parser doit lire **uniquement** `FinalDraft/Content/Paragraph` dans l'ordre.

## 2. Types de `Paragraph` (`@Type`)

| `Type`          | Rôle scénario            | Traitement parser V1 |
|-----------------|--------------------------|----------------------|
| `Scene Heading` | En-tête de scène         | **Démarre une nouvelle séquence.** Source de `scene_number`, `int_ext`, `decor`, `day_night`. |
| `Action`        | Description / didascalie | Ajouté au `raw_text` de la séquence courante. |
| `Character`     | Nom du personnage        | `raw_text` (utile à l'IA pour `personnage`). |
| `Dialogue`      | Réplique                 | `raw_text`. |
| `Parenthetical` | Jeu / ton `(…)`          | `raw_text`. |
| `Transition`    | `CUT TO:`, `FADE OUT.`   | `raw_text` (ne démarre pas de séquence). |
| `Shot`          | Indication de plan       | `raw_text`. |
| `General`       | Texte libre              | `raw_text`. |
| `Cast List`     | Liste de cast            | Ignoré V1. |

Règle de découpage : **chaque `Scene Heading` ouvre une séquence** ; tous les paragraphes suivants
lui appartiennent jusqu'au prochain `Scene Heading`. Le premier texte avant tout `Scene Heading`
(rare) → séquence implicite `parse_status = needs_review`.

## 3. Texte d'un paragraphe — runs `<Text>`

Le texte d'un `<Paragraph>` peut être **fragmenté en plusieurs `<Text>`** (changements de style :
gras, italique, souligné). Il faut **concaténer tous les `<Text>` enfants dans l'ordre**, sans
ajouter d'espace artificiel (les espaces sont dans les runs).

```xml
<Paragraph Type="Dialogue">
  <Text>Tu n'aurais jamais du </Text>
  <Text Style="Italic">le</Text>
  <Text> garder.</Text>
</Paragraph>
```
→ texte = `Tu n'aurais jamais du le garder.`

⚠️ Un `<Paragraph>` peut être **vide** (aucun `<Text>`) → ligne blanche, à ignorer pour le `raw_text`
utile mais sans casser la numérotation.

## 4. `Scene Heading` → champs directs de `sequences`

Le `Scene Heading` alimente les **champs directs** de la table `sequences`
(JAMAIS des `sequence_elements` — voir CLAUDE.md « Champs directs ») :

Forme classique française : `INT./EXT. DÉCOR - MOMENT`

- **`int_ext`** : préfixe → `INT` | `EXT` | `INT/EXT` (variantes `INT.`, `EXT.`, `INT./EXT.`, `INT-EXT`).
  Inconnu → `UNKNOWN`.
- **`decor`** : segment central (entre le préfixe et le ` - MOMENT`). Ex : `APPARTEMENT MARC`.
  Un sous-lieu après une virgule/tiret peut alimenter `sub_decor`.
- **`day_night`** : suffixe après le dernier ` - ` → `JOUR` | `NUIT` | `MATIN` | `SOIR` | `AUBE` | `CREPUSCULE`.
  Inconnu → `UNKNOWN`.
- **`scene_heading`** : la ligne brute complète, conservée telle quelle.

Exemple : `INT. APPARTEMENT MARC - NUIT` → `int_ext=INT`, `decor=APPARTEMENT MARC`, `day_night=NUIT`.

Le parsing du heading est heuristique mais **déterministe** ; en cas d'ambiguïté (heading incomplet,
sans moment, sans préfixe) → valeurs `UNKNOWN` et `parse_status = needs_review`. On **n'appelle pas l'IA**
au parsing.

## 5. `SceneProperties` — numéro & longueur

Sous un `Scene Heading`, Final Draft peut placer :

```xml
<SceneProperties Length="0:30" Page="1" Title="">
  <SceneArc/>
</SceneProperties>
```

- `@Page` : numéro de page de début.
- `@Length` : longueur estimée (format `min:sec` côté FD) — **piste** pour `huitiemes`, mais l'estimation
  fine reste à l'IA. Le parser stocke ce qu'il a ; il ne calcule pas les huitièmes.
- Le **numéro de scène** vient en priorité de l'attribut `Number` du `<Paragraph Number="12" ...>` quand il
  est présent ; sinon `scene_number` reste vide (l'ordre est porté par `display_order`).

## 6. Mapping vers le schéma

| Source FDX                              | Cible                                  |
|-----------------------------------------|----------------------------------------|
| Ordre du `Scene Heading` dans `Content` | `sequences.display_order` (1-based)    |
| `Paragraph@Number`                      | `sequences.scene_number`               |
| Ligne `Scene Heading` brute             | `sequences.scene_heading`              |
| Préfixe heading                         | `sequences.int_ext`                    |
| Décor heading                           | `sequences.decor` / `sub_decor`        |
| Moment heading                          | `sequences.day_night`                  |
| Concat. de tous les paragraphes de la scène | `sequences.raw_text`               |

`resume` et `huitiemes` (champs directs aussi) sont **renseignés par l'IA**, pas par le parser.

## 7. Pièges connus (à valider sur vrais fichiers)

- Plusieurs `<Text>` par paragraphe (styles) → toujours concaténer.
- Heading sans ` - MOMENT` → `day_night = UNKNOWN`.
- Heading sans préfixe `INT/EXT` (ex. `LE PONT`) → `int_ext = UNKNOWN`, `needs_review`.
- `INT./EXT.` mixte → `int_ext = INT/EXT`.
- Caractères accentués / apostrophes typographiques (`’`) → garder l'encodage UTF-8 d'origine.
- Paragraphes `Action` multi-lignes → un `<Paragraph>` par bloc, séparer par `\n`.

## 8. Exemples anonymisés (À COMPLÉTER — Loucman / utilisateur)

> Objectif CLAUDE.md / C-04 : au moins 5 extraits XML **anonymisés** issus de vrais FDX, couvrant :
> `fdx_simple`, `fdx_dialogues_multi_personnages`, `fdx_scene_heading_incomplet`,
> `fdx_scene_sans_heading`, + 1 cas réel varié.

- [ ] Exemple 1 — séquence simple INT/JOUR
- [ ] Exemple 2 — dialogues multi-personnages
- [ ] Exemple 3 — `Scene Heading` incomplet (sans moment)
- [ ] Exemple 4 — texte sans `Scene Heading`
- [ ] Exemple 5 — cas réel anonymisé (transitions, styles, `SceneProperties`)

En attendant, la fixture synthétique
[`sample.fdx.example`](../backend/storage/app/testing/fdx/sample.fdx.example) couvre déjà :
2 `Scene Heading` (INT/NUIT + EXT/NUIT), `Action`, `Character`, `Dialogue`, `Parenthetical`,
`Transition`, runs `<Text>` stylés, `SceneProperties`.
