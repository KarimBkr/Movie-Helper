# API Error Format - Movie Helper

## Error Response Format

```json
{
  "message": "Texte en français descriptif",
  "code": "ERROR_CODE",
  "errors": {
    "field_name": ["Message d'erreur pour ce champ"]
  }
}
```

## Error Codes (C-05)

| Code | HTTP | Description |
|------|------|-------------|
| `UNAUTHORIZED` | 401 | Token absent, expiré ou invalide |
| `FORBIDDEN_PROJECT` | 403 | Utilisateur non membre du projet |
| `PROJECT_NOT_FOUND` | 404 | Projet inexistant ou supprimé |
| `SCRIPT_NOT_FOUND` | 404 | Script inexistant |
| `INVALID_FDX_FILE` | 422 | Fichier FDX non valide |
| `SCRIPT_ALREADY_PARSED` | 409 | Script déjà parsé |
| `ANALYSIS_ALREADY_RUNNING` | 409 | Analyse IA déjà en cours |
| `ANALYSIS_NOT_FOUND` | 404 | Job d'analyse inexistant (L-06) |
| `CLAUDE_TOOL_USE_FAILED` | 500 | Appel Claude échoué (tool use) |
| `INVALID_SEQUENCE_ELEMENT` | 422 | Élément séquence invalide |
| `MISSING_SOURCE_TEXT` | 422 | source_text requis pour élément IA |
| `MEMBER_ALREADY_EXISTS` | 409 | Utilisateur déjà membre du projet |
| `LAST_OWNER_CANNOT_BE_REMOVED` | 422 | Impossible de supprimer le dernier owner |
| `PROJECT_ALREADY_DELETING` | 409 | Projet en cours de suppression |

## Example Error Responses

### 401 Unauthorized
```json
{
  "message": "Token absent ou invalide",
  "code": "UNAUTHORIZED",
  "errors": {}
}
```

### 422 Validation Error
```json
{
  "message": "Données invalides",
  "code": "INVALID_FDX_FILE",
  "errors": {
    "file": ["Le fichier doit avoir l'extension .fdx"],
    "file_size": ["Le fichier ne doit pas dépasser 100MB"]
  }
}
```

### 403 Forbidden
```json
{
  "message": "Vous n'avez pas accès à ce projet",
  "code": "FORBIDDEN_PROJECT",
  "errors": {}
}
```
