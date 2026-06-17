import AuthPageLayout from '@/components/auth/AuthPageLayout'
import AuthForm from '@/components/auth/AuthForm'

export default function LoginPage({
  searchParams,
}: {
  searchParams: { registered?: string }
}) {
  return (
    <AuthPageLayout>
      {searchParams.registered && (
        <div className="mb-6 flex items-start gap-2.5 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
          <svg className="mt-0.5 shrink-0 text-green-600" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
            <circle cx="7" cy="7" r="6.25" stroke="currentColor" strokeWidth="1.5" />
            <path d="M4.5 7l2 2 3-3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
          <p className="text-sm text-green-700 leading-snug">Compte créé. Vous pouvez vous connecter.</p>
        </div>
      )}
      <AuthForm mode="login" />
    </AuthPageLayout>
  )
}
