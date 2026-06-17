import AuthForm from '@/components/auth/AuthForm'

export default function LoginPage({
  searchParams,
}: {
  searchParams: { registered?: string }
}) {
  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-sm space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Movie Helper</h1>
          <p className="mt-1 text-sm text-gray-500">Connexion</p>
        </div>

        {searchParams.registered && (
          <p className="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
            Compte créé. Vous pouvez vous connecter.
          </p>
        )}

        <AuthForm mode="login" />
      </div>
    </main>
  )
}
