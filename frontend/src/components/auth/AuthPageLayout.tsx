import Link from 'next/link'

function ClapperboardIcon() {
  return (
    <svg width="24" height="20" viewBox="0 0 26 22" fill="none" aria-hidden="true">
      <rect x="0.75" y="7.75" width="24.5" height="13.5" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
      <rect x="0.75" y="0.75" width="24.5" height="7.5" rx="1.5" stroke="currentColor" strokeWidth="1.5" />
      <line x1="6.5"  y1="0.75" x2="4"    y2="8.25" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
      <line x1="13"   y1="0.75" x2="10.5" y2="8.25" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
      <line x1="19.5" y1="0.75" x2="17"   y2="8.25" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
    </svg>
  )
}

const features = [
  { label: 'Import FDX',  desc: 'Chargez votre scénario Final Draft directement.' },
  { label: 'Analyse IA',  desc: 'Dépouillement séquence par séquence, vérifié par vous.' },
  { label: 'Export XLSX', desc: 'Tableau prêt pour la production en un clic.' },
]

export default function AuthPageLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen flex">

      {/*
        Panneau gauche — branding
        md  : 45% (tablette, panneau compact)
        xl  : 52% (grand écran, plein effet)
        Masqué sur mobile — le formulaire prend toute la largeur
      */}
      <div className="hidden md:flex md:w-[45%] xl:w-[52%] bg-primary flex-col justify-between px-10 py-10 lg:px-14 lg:py-12 relative overflow-hidden shrink-0">

        {/* Filigrane décoratif — taille proportionnelle à l'écran */}
        <div
          aria-hidden="true"
          className="absolute -right-8 top-1/2 -translate-y-1/2 font-display leading-none text-white/4 select-none pointer-events-none text-[10rem] lg:text-[16rem] xl:text-[20rem]"
        >
          MH
        </div>

        {/* Logo */}
        <Link href="/" className="relative z-10 flex items-center gap-2.5 text-white w-fit">
          <ClapperboardIcon />
          <span className="font-display text-lg tracking-wide">Movie Helper</span>
        </Link>

        {/* Accroche centrale */}
        <div className="relative z-10 space-y-8 lg:space-y-10">
          <div className="space-y-4">
            <h2 className="font-display text-4xl xl:text-5xl leading-tight text-white">
              Du scénario<br />au dépouillement.
            </h2>
            <p className="text-light text-sm lg:text-base max-w-xs leading-relaxed">
              L'IA analyse, vous décidez. Un outil fait pour les assistants réalisateurs.
            </p>
          </div>

          <div className="space-y-3 lg:space-y-4">
            {features.map(({ label, desc }) => (
              <div key={label} className="flex items-start gap-3">
                <div className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-secondary" />
                <div>
                  <span className="text-white text-sm font-medium">{label}</span>
                  <span className="text-light text-sm"> — {desc}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Pied */}
        <p className="relative z-10 text-muted text-xs tracking-widest uppercase">
          Assistant Réalisateur — V1
        </p>
      </div>

      {/*
        Panneau droit — formulaire
        Mobile   : plein écran, défilement naturel depuis le haut
        md+      : centrage vertical dans l'espace restant
        overflow-y-auto : évite la coupure en paysage mobile
      */}
      <div className="flex-1 flex flex-col overflow-y-auto bg-white">
        <div className="flex flex-col justify-start md:justify-center md:min-h-full px-5 py-8 sm:px-8 sm:py-10 md:py-12">
          <div className="w-full max-w-sm mx-auto">

            {/* Logo — mobile uniquement */}
            <div className="md:hidden flex items-center gap-2 text-primary mb-8">
              <ClapperboardIcon />
              <span className="font-display text-xl">Movie Helper</span>
            </div>

            {children}
          </div>
        </div>
      </div>

    </div>
  )
}
