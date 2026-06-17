'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { createClient } from '@/lib/supabase/client'
import { createProject, PROJECT_TYPE_LABELS, type ProjectType } from '@/lib/api/projects'

const PROJECT_TYPES = Object.entries(PROJECT_TYPE_LABELS) as [ProjectType, string][]

export default function CreateProjectForm() {
  const [open, setOpen]       = useState(false)
  const [title, setTitle]     = useState('')
  const [type, setType]       = useState<ProjectType>('film')
  const [error, setError]     = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const router = useRouter()

  const reset = () => {
    setTitle('')
    setType('film')
    setError(null)
    setOpen(false)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)

    try {
      const supabase = createClient()
      const { data: { session } } = await supabase.auth.getSession()
      if (!session) throw new Error('Session expirée.')

      await createProject(session.access_token, title.trim(), type)
      reset()
      router.refresh()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Une erreur est survenue.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <>
      <button
        onClick={() => setOpen(true)}
        className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-secondary active:bg-secondary/90"
      >
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <path d="M7 1v12M1 7h12" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
        </svg>
        Nouveau projet
      </button>

      {open && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4 backdrop-blur-sm"
          onClick={(e) => e.target === e.currentTarget && reset()}
        >
          <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 className="font-display text-2xl text-primary mb-5">Nouveau projet</h2>

            {error && (
              <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {error}
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="space-y-1.5">
                <label htmlFor="proj-title" className="block text-sm font-medium text-primary">
                  Titre
                </label>
                <input
                  id="proj-title"
                  type="text"
                  required
                  autoFocus
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="Mon film"
                  className="block w-full rounded-lg border border-light bg-surface/40 px-4 py-3 text-base text-primary placeholder-muted/60 transition-colors focus:border-secondary focus:bg-white focus:outline-none focus:ring-2 focus:ring-secondary/20"
                />
              </div>

              <div className="space-y-1.5">
                <label htmlFor="proj-type" className="block text-sm font-medium text-primary">
                  Type
                </label>
                <select
                  id="proj-type"
                  value={type}
                  onChange={(e) => setType(e.target.value as ProjectType)}
                  className="block w-full rounded-lg border border-light bg-surface/40 px-4 py-3 text-base text-primary transition-colors focus:border-secondary focus:bg-white focus:outline-none focus:ring-2 focus:ring-secondary/20"
                >
                  {PROJECT_TYPES.map(([value, label]) => (
                    <option key={value} value={value}>{label}</option>
                  ))}
                </select>
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={reset}
                  className="flex-1 rounded-lg border border-light px-4 py-2.5 text-sm font-medium text-muted transition-colors hover:border-muted hover:text-primary"
                >
                  Annuler
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className="flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-secondary disabled:opacity-50"
                >
                  {loading ? 'Création…' : 'Créer'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  )
}
