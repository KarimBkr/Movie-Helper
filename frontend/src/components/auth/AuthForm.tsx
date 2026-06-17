'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import { createClient } from '@/lib/supabase/client'

const SUPABASE_ERRORS: Record<string, string> = {
  'User already registered': 'Un compte existe déjà avec cet email.',
  'Invalid login credentials': 'Email ou mot de passe incorrect.',
  'Password should be at least 6 characters': 'Le mot de passe doit contenir au moins 6 caractères.',
  'Email not confirmed': 'Vérifiez votre email avant de vous connecter.',
}

interface Props {
  mode: 'login' | 'register'
}

export default function AuthForm({ mode }: Props) {
  const [email, setEmail]       = useState('')
  const [password, setPassword] = useState('')
  const [error, setError]       = useState<string | null>(null)
  const [loading, setLoading]   = useState(false)
  const router = useRouter()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)

    const supabase = createClient()

    try {
      if (mode === 'register') {
        const { error } = await supabase.auth.signUp({ email, password })
        if (error) throw error
        router.push('/login?registered=1')
      } else {
        const { error } = await supabase.auth.signInWithPassword({ email, password })
        if (error) throw error
        router.refresh()
        router.push('/projects')
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Une erreur est survenue.'
      setError(SUPABASE_ERRORS[msg] ?? 'Une erreur est survenue. Réessayez.')
    } finally {
      setLoading(false)
    }
  }

  const isLogin = mode === 'login'

  return (
    <div className="space-y-8">

      {/* En-tête du formulaire */}
      <div>
        <p className="text-[0.65rem] tracking-[0.2em] uppercase text-muted mb-2">
          {isLogin ? 'Accès plateforme' : 'Créer un accès'}
        </p>
        <h1 className="font-display text-3xl text-primary">
          {isLogin ? 'Connexion' : 'Inscription'}
        </h1>
      </div>

      {/* Formulaire */}
      <form onSubmit={handleSubmit} className="space-y-6">

        {/* Message d'erreur */}
        {error && (
          <div className="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
            <svg className="mt-0.5 shrink-0 text-red-500" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <circle cx="7" cy="7" r="6.25" stroke="currentColor" strokeWidth="1.5" />
              <line x1="7" y1="4.5" x2="7" y2="7.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
              <circle cx="7" cy="10" r="0.75" fill="currentColor" />
            </svg>
            <p className="text-sm text-red-700 leading-snug">{error}</p>
          </div>
        )}

        {/* Champ email */}
        <div className="space-y-1.5">
          <label htmlFor="email" className="block text-[0.65rem] tracking-[0.18em] uppercase font-medium text-muted">
            Email
          </label>
          <input
            id="email"
            type="email"
            required
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="block w-full border-0 border-b-2 border-light bg-transparent pb-2.5 pt-1 text-base text-primary placeholder-muted/50 focus:border-secondary focus:outline-none transition-colors"
          />
        </div>

        {/* Champ mot de passe */}
        <div className="space-y-1.5">
          <label htmlFor="password" className="block text-[0.65rem] tracking-[0.18em] uppercase font-medium text-muted">
            Mot de passe
          </label>
          <input
            id="password"
            type="password"
            required
            autoComplete={isLogin ? 'current-password' : 'new-password'}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="block w-full border-0 border-b-2 border-light bg-transparent pb-2.5 pt-1 text-base text-primary placeholder-muted/50 focus:border-secondary focus:outline-none transition-colors"
          />
        </div>

        {/* Bouton */}
        <button
          type="submit"
          disabled={loading}
          className="group mt-2 flex w-full items-center justify-between rounded-lg bg-primary px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-secondary disabled:opacity-50"
        >
          <span>{loading ? 'Chargement…' : isLogin ? 'Se connecter' : 'Créer un compte'}</span>
          {!loading && (
            <svg className="transition-transform group-hover:translate-x-1" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          )}
          {loading && (
            <svg className="animate-spin" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <circle cx="8" cy="8" r="6" stroke="currentColor" strokeWidth="1.5" strokeOpacity="0.3" />
              <path d="M8 2a6 6 0 0 1 6 6" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
            </svg>
          )}
        </button>

      </form>

      {/* Lien bas de formulaire */}
      <p className="text-sm text-muted">
        {isLogin ? (
          <>
            Pas encore de compte ?{' '}
            <Link href="/register" className="font-medium text-primary hover:text-secondary transition-colors">
              Créer un accès
            </Link>
          </>
        ) : (
          <>
            Déjà un compte ?{' '}
            <Link href="/login" className="font-medium text-primary hover:text-secondary transition-colors">
              Se connecter
            </Link>
          </>
        )}
      </p>

    </div>
  )
}
