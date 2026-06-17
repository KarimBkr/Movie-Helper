# Conventions — Movie Helper

## Nommage base de données

- Tables : `snake_case`, pluriel (`projects`, `project_members`, `sequence_elements`)
- Colonnes : `snake_case` (`owner_id`, `created_at`, `storage_path`)
- Enums Postgres : `snake_case` (`element_category`, `project_member_role`)
- Index : `{table}_{colonne(s)}_idx`, unique : `{table}_{colonne}_unique`
- Clé primaire : `id uuid` sur toutes les tables

## Routes API Laravel

Format REST strict, préfixe `/api` :

| Méthode | Pattern | Exemple |
|---------|---------|---------|
| GET | `/api/ressources` | `/api/projects` |
| POST | `/api/ressources` | `/api/projects` |
| GET | `/api/ressources/{id}` | `/api/projects/{id}` |
| PATCH | `/api/ressources/{id}/action` | `/api/sequences/{id}/validate` |
| DELETE | `/api/ressources/{id}` | `/api/projects/{id}` |

## Rôles

| Technique | Libellé UI | Droits |
|-----------|-----------|--------|
| `owner` | 1er assistant réalisateur | Tout |
| `viewer` | 2e assistant / production | Lecture + export XLSX |

Laravel ignore tout `role` ou `owner_id` venant du front.

## Statuts

```
project_status          : active | deleting | deleted
script_parse_status     : uploaded | parsing | parsed | parse_failed
sequence_status         : ia | verifie | corrige | manuel
element_status          : ia | verifie | corrige | manuel
confidence_level        : high | medium | low
analysis_job_status     : pending | running | completed | partial | failed | cancelled
analysis_item_status    : pending | running | completed | failed | skipped
```

## Catégories `sequence_elements.category`

Autorisées en V1 :
```
personnage | accessoire | vehicule | arme | animal | sfx_vfx | costume | continuite | flag | note
```

Jamais des éléments (stockés directement sur `sequences`) :
```
resume | decor | int_ext | jour_nuit | huitiemes
```

Hors V1 → rejeter ou grouper en `note` :
```
maquillage | coiffure | son | musique | cascades | figuration
```

## Format des erreurs API

```json
{ "message": "Texte en français", "code": "ERROR_CODE", "errors": {} }
```

Codes d'erreur :
```
UNAUTHORIZED | FORBIDDEN_PROJECT | PROJECT_NOT_FOUND | SCRIPT_NOT_FOUND
INVALID_FDX_FILE | SCRIPT_ALREADY_PARSED | ANALYSIS_ALREADY_RUNNING
CLAUDE_TOOL_USE_FAILED | INVALID_SEQUENCE_ELEMENT | MISSING_SOURCE_TEXT
MEMBER_ALREADY_EXISTS | LAST_OWNER_CANNOT_BE_REMOVED | PROJECT_ALREADY_DELETING
```

## Variables d'environnement

### Laravel — `backend/.env`

| Variable | Description |
|----------|-------------|
| `SUPABASE_URL` | URL du projet Supabase |
| `SUPABASE_ANON_KEY` | Clé publique anon |
| `SUPABASE_SERVICE_ROLE_KEY` | **Serveur uniquement.** Jamais côté Next.js |
| `SUPABASE_JWT_SECRET` | Secret JWT pour vérification tokens |
| `SUPABASE_VERIFY_JWT_MODE` | `jwt_secret` (défaut) ou `jwks` |
| `SUPABASE_STORAGE_FDX_BUCKET` | `fdx-files` |
| `SUPABASE_STORAGE_EXPORTS_BUCKET` | `exports` |
| `ANTHROPIC_API_KEY` | **Serveur uniquement.** Jamais côté Next.js |
| `ANTHROPIC_MODEL` | `claude-sonnet-4-6` |
| `ANTHROPIC_MAX_TOKENS` | `4000` |
| `ANTHROPIC_TIMEOUT_SECONDS` | `120` |
| `QUEUE_CONNECTION` | `database` (local) / `redis` (prod) |
| `AI_QUEUE_NAME` | `ai` |

### Next.js — `frontend/.env.local`

| Variable | Description |
|----------|-------------|
| `NEXT_PUBLIC_API_URL` | URL Laravel (`http://localhost:8000/api`) |
| `NEXT_PUBLIC_SUPABASE_URL` | URL Supabase |
| `NEXT_PUBLIC_SUPABASE_ANON_KEY` | Clé anon publique |

**Interdites côté Next.js :** `SUPABASE_SERVICE_ROLE_KEY`, `ANTHROPIC_API_KEY`, `SUPABASE_JWT_SECRET`, `DB_PASSWORD`

## Conventions code Laravel

- Actions pour la logique métier (`app/Actions/`)
- DTOs pour les réponses structurées (`app/DTO/`)
- Services pour les opérations réutilisables (`app/Services/`)
- Vérification membership avant toute opération projet via `ProjectAccessService`
- Transactions DB pour toutes les opérations multi-tables
- Soft delete sur `sequence_elements` (`deleted_at`)
- Réponses d'erreur via `ApiError` (`app/Support/ApiError.php`)

## Conventions code Next.js

- App Router uniquement — zéro Pages Router
- Zéro logique métier dans les composants
- `Authorization: Bearer <token>` dans chaque requête vers Laravel
- Zéro appel Supabase direct pour les données métier
- Token récupéré via `getSession()` côté serveur ou `createClientComponentClient()` côté client

## Sécurité multi-tenant — règles absolues

- Laravel vérifie toujours que l'utilisateur est membre du projet via `ProjectAccessService`
- Aucune route projet ne se fie uniquement au `project_id` reçu du front
- `owner_id` et `role` sont toujours issus du token Supabase vérifié par Laravel
- La `service_role_key` Supabase n'est utilisée qu'après vérification métier explicite
