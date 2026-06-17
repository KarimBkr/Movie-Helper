import AuthForm from '@/components/auth/AuthForm'

export default function RegisterPage() {
  return (
    <main className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-sm space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Movie Helper</h1>
          <p className="mt-1 text-sm text-gray-500">Créer un compte</p>
        </div>

        <AuthForm mode="register" />
      </div>
    </main>
  )
}
