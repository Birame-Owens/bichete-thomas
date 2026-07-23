import { ArrowLeft, ShoppingCart } from 'lucide-react'
import { panierCount, usePanier } from './panier'

// Bandeau commun des pages boutique : retour, logo, panier avec badge.
export function BoutiqueHeader({ backHref = '/', backLabel = 'Retour au salon' }: {
  backHref?: string
  backLabel?: string
}) {
  const items = usePanier()
  const count = panierCount(items)

  return (
    <header className="sticky top-0 z-30 border-b border-[#f7d6e5] bg-white/95 backdrop-blur">
      <div className="mx-auto flex w-full max-w-[1440px] items-center gap-3 px-3 py-3 sm:px-5 lg:px-8">
        <a
          href={backHref}
          className="inline-flex h-10 items-center gap-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600 transition hover:text-[#f31976]"
        >
          <ArrowLeft className="h-4 w-4" />
          <span className="hidden sm:inline">{backLabel}</span>
          <span className="sm:hidden">Retour</span>
        </a>

        <div className="mx-auto flex items-center gap-3">
          <img src="/logo-bichette.jpg" alt="Bichette Thomas" className="h-10 w-10 rounded-2xl object-cover" />
          <p className="font-display text-xl text-slate-950">
            Boutique <span className="text-[#f31976]">Bichette</span>
          </p>
        </div>

        <a
          href="/boutique/panier"
          className="relative grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#fff0f6] text-[#f31976] transition hover:scale-105 active:scale-95"
          aria-label="Panier"
        >
          <ShoppingCart className="h-5 w-5" />
          {count > 0 ? (
            <span className="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-[#f31976] px-1 text-[10px] font-black text-white">
              {count > 99 ? '99+' : count}
            </span>
          ) : null}
        </a>
      </div>
    </header>
  )
}
