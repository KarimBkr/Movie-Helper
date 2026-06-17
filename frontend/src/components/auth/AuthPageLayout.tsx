import Link from 'next/link'

function ClapperboardIcon() {
  return (
    <svg width="26" height="22" viewBox="0 0 26 22" fill="none" aria-hidden="true">
      <rect x="0.75" y="7.75" width="24.5" height="13.5" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
      <rect x="0.75" y="0.75" width="24.5" height="7.5" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
      <line x1="6.5"  y1="0.75" x2="4"   y2="8.25" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
      <line x1="13"   y1="0.75" x2="10.5" y2="8.25" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
      <line x1="19.5" y1="0.75" x2="17"  y2="8.25" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
    </svg>
  )
}

interface Props {
  children: React.ReactNode
}

export default function AuthPageLayout({ children }: Props) {
  return (
    <div className="min-h-screen flex">

      {/* ── Panneau gauche — branding cinéma ── */}
      <div className="hidden md:flex md:w-[52%] bg-primary flex-col justify-between px-14 py-12 relative overflow-hidden">

        {/* Filigrane décoratif */}
        <div
          aria-hidden="true"
          className="absolute -right-16 top-1/2 -translate-y-1/2 font-display text-[22rem] leading-none text-white/[0.04] select-none pointer-events-none"
        >
          MH
        </div>

        {/* Logo */}
        <Link href="/" className="relative z-10 flex items-center gap-2.5 text-white w-fit">
          <ClapperboardIcon />
          <span className="font-display text-lg tracking-wide">Movie Helper</span>
        </Link>

        {/* Accroche */}
        <div className="relative z-10 space-y-7">
          <h2 className="font-display text-[2.75rem] leading-[1.15] text-white">
            Du scénario<br />au dépouillement,<br />séquence par séquence.
          </h2>
          <div className="w-10 h-px bg-secondary" />
          <p className="text-light text-base leading-relaxed max-w-xs">
            Analyse IA du script FDX.<br />
            <span className="text-white font-medium">L'IA propose. L'assistant décide.</span>
          </p>
        </div>

        {/* Pied de panneau */}
        <p className="relative z-10 text-muted text-[0.65rem] tracking-[0.2em] uppercase">
          Assistant Réalisateur — V1
        </p>
      </div>

      {/* ── Panneau droit — formulaire ── */}
      <div className="flex-1 flex items-center justify-center bg-white px-8 py-12">
        <div className="w-full max-w-sm">

          {/* Logo mobile uniquement */}
          <div className="md:hidden flex items-center gap-2 text-primary mb-10">
            <ClapperboardIcon />
            <span className="font-display text-xl">Movie Helper</span>
          </div>

          {children}
        </div>
      </div>

    </div>
  )
}
