import type { Project } from '@/lib/api/projects'
import { PROJECT_TYPE_LABELS } from '@/lib/api/projects'

export default function ProjectCard({ project }: { project: Project }) {
  const date = new Date(project.created_at).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })

  return (
    <article className="group flex flex-col gap-4 rounded-xl border border-light bg-white p-5 transition-shadow hover:shadow-md">
      <div className="flex items-start justify-between gap-3">
        <h2 className="font-display text-lg leading-snug text-primary line-clamp-2">
          {project.title}
        </h2>
        <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium ${
          project.role === 'owner'
            ? 'bg-primary/10 text-primary'
            : 'bg-muted/10 text-muted'
        }`}>
          {project.role === 'owner' ? '1er AD' : 'Lecture'}
        </span>
      </div>

      <div className="flex items-center justify-between">
        <span className="text-sm text-muted">
          {PROJECT_TYPE_LABELS[project.type]}
        </span>
        <time className="text-xs text-muted" dateTime={project.created_at}>
          {date}
        </time>
      </div>

      <div className="mt-auto pt-1 border-t border-light/60">
        <span className="font-mono text-xs text-light">{project.slug}</span>
      </div>
    </article>
  )
}
