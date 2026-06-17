# CLAUDE.md — Movie Helper

## Projet
Outil SaaS qui transforme un scénario Final Draft (.fdx) en pré-dépouillement vérifiable par IA.
**Principe : L'IA propose. L'assistant décide.**

Flux : `FDX → parsing XML → analyse Claude séquence par séquence → correction humaine → export XLSX`

---

## Stack
```
Frontend  : Next.js App Router        → /frontend
Backend   : Laravel                   → /backend
Base      : Supabase (Postgres + RLS + Auth + Storage)
IA        : Claude API claude-sonnet-4-6
Queue     : Laravel Queue (database local, Redis prod)
```

---

## Règles architecture — non négociables

**1. Laravel = seule source de vérité pour les écritures**
Next.js ne touche jamais Supabase directement pour les données métier. Tout passe par Laravel API.

**2. Secrets côté Laravel uniquement. Jamais dans frontend/.env.local :**
```
SUPABASE_SERVICE_ROLE_KEY
ANTHROPIC_API_KEY
SUPABASE_JWT_SECRET
DB_PASSWORD
```

**3. Claude = tool_use strict, jamais JSON libre**
```php
// Toujours :
tool_choice: { type: "tool", name: "extract_sequence_breakdown" }
strict: true
// input_schema COMPLET injecté — jamais {}
// Lire uniquement : content[type=tool_use].input
```

**4. RLS activée sur toutes les tables Supabase**
Laravel utilise service role key uniquement après vérification via `ProjectAccessService`.

**5. Jobs IA : max 2 workers parallèles**
```php
// AnalyzeSequenceJob
public int $tries = 2;
public int $backoff = 30;
// Queue name : ai
```

**6. Parser FDX = déterministe. L'IA enrichit, elle ne compense pas un mauvais parsing.**

**7. Tout élément IA sans source_text est rejeté par Laravel.**
Contrainte aussi en base :
```sql
constraint sequence_elements_ai_source_required check (
  status <> 'ia' OR (source_text IS NOT NULL AND char_length(trim(source_text)) > 0)
)
```

---

## Modèle de données

```
profiles / projects / project_members / scripts
sequences / sequence_elements / analysis_jobs / analysis_job_items / exports
```

`project_id` est présent sur toutes les tables → RLS + suppression propre.

### Rôles
| Technique | UI | Droits |
|-----------|-----|--------|
| `owner` | 1er assistant réalisateur | Tout |
| `viewer` | 2e assistant / production | Lecture + export XLSX |

Laravel ignore tout `owner_id` ou `role` venant du front.

---

## Catégories V1

### `sequence_elements.category` (enum) :
```
personnage / accessoire / vehicule / arme / animal
sfx_vfx / costume / continuite / flag / note
```

### Champs directs sur `sequences` (PAS des elements) :
```
resume / decor / int_ext / jour_nuit / huitiemes
```

Ne jamais créer un `sequence_element` avec ces catégories.

### Hors V1 → rejeter ou grouper en `note` :
```
maquillage / coiffure / son / musique / cascades / figuration
```

---

## Statuts

```
sequence_status         : ia | verifie | corrige | manuel
element_status          : ia | verifie | corrige | manuel
confidence_level        : high | medium | low
analysis_job_status     : pending | running | completed | partial | failed | cancelled
analysis_item_status    : pending | running | completed | failed | skipped
```

---

## Format réponse Claude

```json
{
  "sequence": {
    "scene_number": "12",
    "resume":    { "value": "...", "source_text": "...", "confidence": "high" },
    "decor":     { "value": "...", "source_text": "...", "confidence": "high" },
    "int_ext":   { "value": "INT", "source_text": "...", "confidence": "high" },
    "jour_nuit": { "value": "NUIT", "source_text": "...", "confidence": "high" },
    "huitiemes": { "value": 2, "source_text": "...", "confidence": "medium" }
  },
  "elements": [
    { "category": "accessoire", "value": "revolver",
      "source_text": "Marc pose le revolver sur la table.",
      "confidence": "high", "note": "" }
  ],
  "flags": [],
  "notes": []
}
```

Traitement Laravel après réponse :
```
1. Trouver block type=tool_use
2. Vérifier name=extract_sequence_breakdown
3. Lire input
4. Rejeter éléments sans source_text
5. Sauvegarder sequence fields → sequences
6. Sauvegarder elements → sequence_elements
```

---

## Endpoints API Laravel

```
POST   /api/projects
GET    /api/projects
GET    /api/projects/{id}
DELETE /api/projects/{id}

POST   /api/projects/{id}/members
GET    /api/projects/{id}/members
DELETE /api/projects/{id}/members/{userId}

POST   /api/projects/{id}/scripts/upload
POST   /api/projects/{id}/scripts/{scriptId}/parse

POST   /api/projects/{id}/analysis/start
GET    /api/projects/{id}/analysis/{jobId}
POST   /api/projects/{id}/analysis/{jobId}/retry-failed

GET    /api/projects/{id}/sequences
GET    /api/projects/{id}/sequences/{sequenceId}
PATCH  /api/sequences/{sequenceId}/validate

POST   /api/sequences/{sequenceId}/elements
PATCH  /api/elements/{elementId}
DELETE /api/elements/{elementId}

GET    /api/projects/{id}/export/xlsx
```

---

## Format erreur API

```json
{ "message": "Texte en français", "code": "ERROR_CODE", "errors": {} }
```

Codes : `UNAUTHORIZED` / `FORBIDDEN_PROJECT` / `PROJECT_NOT_FOUND` / `SCRIPT_NOT_FOUND` /
`INVALID_FDX_FILE` / `SCRIPT_ALREADY_PARSED` / `ANALYSIS_ALREADY_RUNNING` /
`CLAUDE_TOOL_USE_FAILED` / `INVALID_SEQUENCE_ELEMENT` / `MISSING_SOURCE_TEXT` /
`MEMBER_ALREADY_EXISTS` / `LAST_OWNER_CANNOT_BE_REMOVED` / `PROJECT_ALREADY_DELETING`

---

## Storage Supabase

```
fdx-files/{project_id}/{script_id}/original.fdx   → bucket privé
exports/{project_id}/exports/{export_id}.xlsx      → bucket privé
```

Chemins commencent toujours par `project_id` pour les policies RLS.

---

## Slug projet

```php
// ProjectSlugService.php
$slug = Str::slug($title) . '-' . Str::lower(Str::random(6));
// ex: "mon-film-8f3a2c"
// Unique globalement. Régénérer si collision.
// Le front ne transmet jamais de slug.
```

---

## Conventions code

### Laravel
- Actions pour la logique métier (pas de fat controllers)
- DTOs pour les réponses Claude
- Toujours vérifier membership avant toute opération
- Transactions DB pour opérations multi-tables
- Soft delete sur `sequence_elements` (champ `deleted_at`)

### Next.js
- App Router uniquement
- Zéro logique métier dans les composants
- Token Supabase dans chaque requête Laravel : `Authorization: Bearer <token>`
- Zéro appel Supabase direct pour données métier

---

## Répartition

**Jihad** → Auth, projets, membres, accès, export, suppression (J-01 à J-08)
**Loucman** → FDX, parsing, IA, tableau, correction (L-01 à L-09)
**Commun** → Schema SQL, tool_use schema, conventions, queue config (C-01 à C-06)

---

## Hors périmètre V1 — ne pas coder

```
Stripe / PDF / Board / Planning / Comparaison versions
WebSockets / Notifications / Budget / Paie / Régie
Intégrations externes / App mobile / Analytics avancé
Rôles personnalisables / Invitation email automatique
```

> Avant d'ajouter quoi que ce soit : "Est-ce que ça aide à passer de FDX à XLSX plus vite ?" → Si non, dehors.

---

## Commandes

```bash
# Laravel
php artisan serve
php artisan migrate
php artisan test
php artisan queue:work --queue=ai --sleep=2 --tries=2 --timeout=120

# Next.js
cd frontend && npm run dev
```

---

## Workflow Git — règles automatiques

### Structure des branches
```
main   → branche protégée, jamais de push direct
dev    → branche d'intégration commune (Jihad + Loucman)
feature/* → une branche par feature, par développeur
```

### Nomenclature des branches feature
```
feature/auth-supabase
feature/middleware-laravel
feature/creation-projets
feature/acces-projet
feature/membres-viewer
feature/export-xlsx
feature/suppression-projet
```

### Démarrer une feature
```bash
git checkout dev
git pull origin dev
git checkout -b feature/nom-de-la-feature
```

### Checklist avant chaque commit — obligatoire
```
✓ Aucune feature existante cassée
✓ Aucun console.log / dd() / var_dump() / dump() dans le code
✓ Aucune clé secrète dans les fichiers (.env dans .gitignore)
✓ Aucune dépendance inutile ajoutée
✓ Pas d'abstraction superflue — si c'est simple, ça reste simple
✓ Pas de code mort ou commenté
✓ Pas de fichier .env / .env.local / .env.*.local commité
```

### Format des commits — français, concis, une ligne
```
feat: authentification Supabase côté Next.js
feat: middleware vérification token Laravel
feat: création et liste des projets
feat: middleware accès projet centralisé
feat: gestion membres viewer
feat: export XLSX dépouillement
feat: suppression complète projet
fix: correction slug en cas de collision
fix: rejet élément IA sans source_text
refactor: centralisation ProjectAccessService
```

### Finir une feature
```bash
git add .
git commit -m "feat: description concise en français"
git push origin feature/nom-de-la-feature
# Signaler que la branche est prête — ne jamais merger soi-même sur main
# La feature est mergée sur dev après validation
```

### .gitignore — doit couvrir
```
.env
.env.local
.env.*.local
node_modules/
vendor/
.DS_Store
storage/app/testing/fdx/*.fdx
```