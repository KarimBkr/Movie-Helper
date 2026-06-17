import { redirect } from 'next/navigation'
import Link from 'next/link'
import { getAccessToken } from '@/lib/auth/getSession'
import { createServerSupabaseClient } from '@/lib/supabase/server'
import { fetchProjects } from '@/lib/api/projects'
import ProjectCard from '@/components/projects/ProjectCard'
import CreateProjectForm from '@/components/projects/CreateProjectForm'

function ClapperboardIcon() {
  return (
    <svg width="20" height="17" viewBox="0 0 26 22" fill="none" aria-hidden="true">
      <rect x="0.75" y="7.75" width="24.5" height="13.5" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
      <rect x="0.75" y="0.75" width="24.5" height="7.5" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
      <line x1="6.5"  y1="0.75" x2="4"    y2="8.25" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
      <line x1="13"   y1="0.75" x2="10.5" y2="8.25" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
      <line x1="19.5" y1="0.75" x2="17"   y2="8.25" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
    </svg>
  )
}

export default async function ProjectsPage() {
  const token = await getAccessToken()
  if (!token) redirect('/login')

  const supabase = await createServerSupabaseClient()
  const { data: { user } } = await supabase.auth.getUser()

  const projects = await fetchProjects(token).catch(() => [])

  return (
    <div className="min-h-screen bg-surface">

      {/* Barre de navigation */}
      <header className="border-b border-light/60 bg-white">
        <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6">
          <Link href="/projects" className="flex items-center gap-2 text-primary">
            <ClapperboardIcon />
            <span className="font-display text-base tracking-wide">Movie Helper</span>
          </Link>

          <div className="flex items-center gap-4">
            <span className="hidden text-sm text-muted sm:block">{user?.email}</span>
            <form action="/logout" method="POST">
              <button
                type="submit"
                className="text-sm font-medium text-muted transition-colors hover:text-primary"
              >
                Déconnexion
              </button>
            </form>
          </div>
        </div>
      </header>

      {/* Contenu */}
      <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">

        {/* En-tête page */}
        <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="font-display text-3xl text-primary">Mes projets</h1>
            <p className="mt-1 text-sm text-muted">
              {projects.length === 0
                ? 'Aucun projet pour le moment.'
                : `${projects.length} projet${projects.length > 1 ? 's' : ''}`}
            </p>
          </div>
          <CreateProjectForm />
        </div>

        {/* Liste */}
        {projects.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-light bg-white py-20 text-center">
            <div className="mb-4 text-muted opacity-40">
              <ClapperboardIcon />
            </div>
            <p className="font-display text-xl text-primary">Aucun projet</p>
            <p className="mt-2 text-sm text-muted max-w-xs">
              Créez votre premier projet pour commencer le dépouillement de votre scénario.
            </p>
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {projects.map((project) => (
              <ProjectCard key={project.id} project={project} />
            ))}
          </div>
        )}
      </main>
    </div>
  )
}
