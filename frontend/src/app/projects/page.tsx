import { redirect } from 'next/navigation'
import Link from 'next/link'
import { getAccessToken } from '@/lib/auth/getSession'
import { createServerSupabaseClient } from '@/lib/supabase/server'
import { fetchProjects } from '@/lib/api/projects'
import ProjectCard from '@/components/projects/ProjectCard'
import CreateProjectForm from '@/components/projects/CreateProjectForm'

function ClapperboardIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 26 22" fill="none" aria-hidden="true" className={className}>
      <rect x="0.75" y="7.75" width="24.5" height="13.5" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
      <rect x="0.75" y="0.75" width="24.5" height="7.5"  rx="1.5" stroke="currentColor" strokeWidth="1.5" />
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

      {/* Header — même DA que le panneau auth */}
      <header className="bg-primary relative overflow-hidden">
        <div
          aria-hidden="true"
          className="absolute -right-4 top-1/2 -translate-y-1/2 font-display leading-none text-white/4 select-none pointer-events-none text-[8rem]"
        >
          MH
        </div>
        <div className="relative mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
          <Link href="/projects" className="flex items-center gap-2.5 text-white">
            <ClapperboardIcon className="w-5 h-auto" />
            <span className="font-display text-base tracking-wide">Movie Helper</span>
          </Link>

          <div className="flex items-center gap-5">
            <span className="hidden text-sm text-light/70 sm:block">{user?.email}</span>
            <form action="/logout" method="POST">
              <button
                type="submit"
                className="text-sm font-medium text-white/60 transition-colors hover:text-white"
              >
                Déconnexion
              </button>
            </form>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12">

        {/* En-tête page */}
        <div className="mb-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p className="text-xs font-medium uppercase tracking-[0.2em] text-muted mb-2">
              Tableau de bord
            </p>
            <h1 className="font-display text-4xl sm:text-5xl text-primary leading-tight">
              Mes projets
            </h1>
            {projects.length > 0 && (
              <p className="mt-2 text-sm text-muted">
                {projects.length} projet{projects.length > 1 ? 's' : ''}
              </p>
            )}
          </div>
          {projects.length > 0 && <CreateProjectForm />}
        </div>

        {/* État vide — même DA que panneau gauche auth */}
        {projects.length === 0 ? (
          <div className="relative overflow-hidden rounded-2xl bg-primary px-8 py-20 text-center">
            <div
              aria-hidden="true"
              className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 font-display leading-none text-white/4 select-none pointer-events-none text-[18rem]"
            >
              MH
            </div>

            <div className="relative flex flex-col items-center gap-6">
              <div className="flex items-center justify-center w-16 h-16 rounded-xl bg-white/10 border border-white/20">
                <ClapperboardIcon className="w-8 h-auto text-white" />
              </div>

              <div>
                <p className="font-display text-3xl text-white">Aucun projet</p>
                <p className="mt-2 text-sm text-light leading-relaxed max-w-xs mx-auto">
                  Créez votre premier projet pour commencer le dépouillement de votre scénario.
                </p>
              </div>

              <div className="flex items-center gap-3 mt-2">
                <div className="h-1.5 w-1.5 rounded-full bg-secondary" />
                <span className="text-light text-sm">Import FDX — Final Draft directement</span>
              </div>
              <div className="flex items-center gap-3 -mt-3">
                <div className="h-1.5 w-1.5 rounded-full bg-secondary" />
                <span className="text-light text-sm">Analyse IA — séquence par séquence</span>
              </div>
              <div className="flex items-center gap-3 -mt-3">
                <div className="h-1.5 w-1.5 rounded-full bg-secondary" />
                <span className="text-light text-sm">Export XLSX — prêt pour la production</span>
              </div>

              <CreateProjectForm variant="light" />
            </div>
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
