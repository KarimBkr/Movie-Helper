const API_URL = process.env.NEXT_PUBLIC_API_URL

export type ProjectType = 'film' | 'serie' | 'court_metrage' | 'pilote' | 'autre'
export type ProjectRole = 'owner' | 'viewer'

export interface Project {
  id: string
  title: string
  slug: string
  type: ProjectType
  role: ProjectRole
  created_at: string
}

export const PROJECT_TYPE_LABELS: Record<ProjectType, string> = {
  film:           'Film',
  serie:          'Série',
  court_metrage:  'Court-métrage',
  pilote:         'Pilote',
  autre:          'Autre',
}

export async function fetchProjects(token: string): Promise<Project[]> {
  const res = await fetch(`${API_URL}/api/projects`, {
    headers: { Authorization: `Bearer ${token}` },
    cache: 'no-store',
  })
  if (!res.ok) throw new Error('Impossible de charger les projets.')
  const json = await res.json()
  return json.data
}

export async function createProject(
  token: string,
  title: string,
  type: ProjectType,
): Promise<Project> {
  const res = await fetch(`${API_URL}/api/projects`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ title, type }),
  })
  const json = await res.json()
  if (!res.ok) throw new Error(json.message ?? 'Impossible de créer le projet.')
  return json.data
}
