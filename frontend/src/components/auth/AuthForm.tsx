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
    <div className="space-y-6 sm:space-y-8">

      {/* En-tête */}
      <div className="space-y-1">
        <h1 className="font-display text-3xl sm:text-4xl text-primary">
          {isLogin ? 'Connexion' : 'Créer un compte'}
        </h1>
        <p className="text-sm text-muted">
          {isLogin
            ? 'Accédez à vos projets de dépouillement.'
            : 'Commencez à analyser vos scénarios.'}
        </p>
      </div>

      {/* Formulaire */}
      <form onSubmit={handleSubmit} className="space-y-4 sm:space-y-5">

        {/* Erreur */}
        {error && (
          <div className="flex gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
            <svg className="mt-0.5 shrink-0 text-red-500" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <circle cx="7" cy="7" r="6.25" stroke="currentColor" strokeWidth="1.5" />
              <line x1="7" y1="4.5" x2="7" y2="7.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
              <circle cx="7" cy="10" r="0.75" fill="currentColor" />
            </svg>
            <p className="text-sm text-red-700 leading-snug">{error}</p>
          </div>
        )}

        {/* Email */}
        <div className="space-y-1.5">
          <label htmlFor="email" className="block text-sm font-medium text-primary">
            Email
          </label>
          <input
            id="email"
            type="email"
            required
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="vous@exemple.com"
            className="block w-full rounded-lg border border-light bg-surface/40 px-4 py-3 text-base text-primary placeholder-muted/60 transition-colors focus:border-secondary focus:bg-white focus:outline-none focus:ring-2 focus:ring-secondary/20"
          />
        </div>

        {/* Mot de passe */}
        <div className="space-y-1.5">
          <label htmlFor="password" className="block text-sm font-medium text-primary">
            Mot de passe
          </label>
          <input
            id="password"
            type="password"
            required
            autoComplete={isLogin ? 'current-password' : 'new-password'}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder={isLogin ? '••••••••' : '8 caractères minimum'}
            className="block w-full rounded-lg border border-light bg-surface/40 px-4 py-3 text-base text-primary placeholder-muted/60 transition-colors focus:border-secondary focus:bg-white focus:outline-none focus:ring-2 focus:ring-secondary/20"
          />
        </div>

        {/* Bouton — touch target 48px minimum (py-3.5 + text-base) */}
        <button
          type="submit"
          disabled={loading}
          className="group mt-1 flex w-full items-center justify-between rounded-lg bg-primary px-5 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-secondary active:bg-secondary/90 disabled:opacity-50"
        >
          <span>{loading ? 'Chargement…' : isLogin ? 'Se connecter' : 'Créer mon compte'}</span>
          {loading ? (
            <svg className="animate-spin" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <circle cx="8" cy="8" r="6" stroke="currentColor" strokeWidth="1.5" strokeOpacity="0.3" />
              <path d="M8 2a6 6 0 0 1 6 6" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
            </svg>
          ) : (
            <svg className="transition-transform group-hover:translate-x-1" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          )}
        </button>

      </form>

      {/* Lien bas */}
      <p className="text-sm text-muted">
        {isLogin ? (
          <>
            Pas encore de compte ?{' '}
            <Link href="/register" className="font-medium text-primary transition-colors hover:text-secondary">
              Créer un compte
            </Link>
          </>
        ) : (
          <>
            Déjà un compte ?{' '}
            <Link href="/login" className="font-medium text-primary transition-colors hover:text-secondary">
              Se connecter
            </Link>
          </>
        )}
      </p>

    </div>
  )
}
