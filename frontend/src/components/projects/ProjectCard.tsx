import type { Project } from '@/lib/api/projects'
import { PROJECT_TYPE_LABELS } from '@/lib/api/projects'

export default function ProjectCard({ project }: { project: Project }) {
  const date = new Date(project.created_at).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })

  const isOwner = project.role === 'owner'

  return (
    <article className="group relative flex flex-col rounded-xl overflow-hidden border border-light bg-white transition-all hover:shadow-lg hover:-translate-y-0.5 cursor-pointer">

      {/* Bande colorée haute — même langage que la DA */}
      <div className={`h-1 w-full ${isOwner ? 'bg-primary' : 'bg-muted'}`} />

      <div className="flex flex-col gap-4 p-5 flex-1">
        <div className="flex items-start justify-between gap-3">
          <h2 className="font-display text-lg leading-snug text-primary line-clamp-2 group-hover:text-secondary transition-colors">
            {project.title}
          </h2>
          <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium ${
            isOwner ? 'bg-primary/10 text-primary' : 'bg-muted/10 text-muted'
          }`}>
            {isOwner ? '1er AD' : 'Lecture'}
          </span>
        </div>

        <p className="text-sm text-muted">{PROJECT_TYPE_LABELS[project.type]}</p>
      </div>

      {/* Pied de carte */}
      <div className="px-5 pb-5 pt-3 border-t border-light/60 flex items-center justify-between">
        <div>
          <span className="font-mono text-xs text-light block">{project.slug}</span>
          <time className="text-xs text-muted" dateTime={project.created_at}>{date}</time>
        </div>
        <svg
          className="w-4 h-4 text-light group-hover:text-secondary group-hover:translate-x-0.5 transition-all"
          viewBox="0 0 16 16" fill="none" aria-hidden="true"
        >
          <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      </div>

    </article>
  )
}
