import { createServerClient } from '@supabase/ssr'
import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

export async function middleware(req: NextRequest) {
  let supabaseResponse = NextResponse.next({ request: req })

  const supabase = createServerClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL!,
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!,
    {
      cookies: {
        getAll: () => req.cookies.getAll(),
        setAll: (cookiesToSet) => {
          cookiesToSet.forEach(({ name, value }) => req.cookies.set(name, value))
          supabaseResponse = NextResponse.next({ request: req })
          cookiesToSet.forEach(({ name, value, options }) =>
            supabaseResponse.cookies.set(name, value, options)
          )
        },
      },
    }
  )

  const { data: { user } } = await supabase.auth.getUser()

  const isAuthPage = req.nextUrl.pathname === '/login' || req.nextUrl.pathname === '/register'
  const isProjectPage = req.nextUrl.pathname.startsWith('/projects')

  if (!user && isProjectPage) {
    return NextResponse.redirect(new URL('/login', req.url))
  }

  if (user && isAuthPage) {
    return NextResponse.redirect(new URL('/projects', req.url))
  }

  return supabaseResponse
}

export const config = {
  matcher: ['/projects/:path*', '/login', '/register'],
}
