import AuthPageLayout from '@/components/auth/AuthPageLayout'
import AuthForm from '@/components/auth/AuthForm'

export default function RegisterPage() {
  return (
    <AuthPageLayout>
      <AuthForm mode="register" />
    </AuthPageLayout>
  )
}
