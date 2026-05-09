import { memo } from 'react'
import { ChevronRight, Heart } from 'lucide-react'
import { coiffureImage, formatCurrency, formatDuration } from '../client.helpers'
import type { ClientCoiffure } from '../client.types'

type CoiffureCardProps = {
  coiffure: ClientCoiffure
  isFavorite: boolean
  devise: string
  onToggleFavorite: (id: number) => void
  onOpenDetails: (coiffure: ClientCoiffure) => void
}

// Carte coiffure du catalogue. memo() est l optimisation cle (I11) :
// avant, chaque frappe dans la barre de recherche OU dans le formulaire
// de reservation provoquait un re-render des 8 cartes affichees, meme si
// leur prop n avait pas change. Sur mobile en pic Tabaski, ca laguait
// visiblement.
//
// Pour que memo bite reellement :
// - le parent doit passer des callbacks stables (useCallback) ;
// - isFavorite est passe en booleen pre-calcule (sinon passer le tableau
//   favoriteIds entier casserait le memo a chaque modif d un favori) ;
// - les autres props (coiffure, devise) sont stables tant que le catalogue
//   et les settings ne changent pas.
function CoiffureCardBase({
  coiffure,
  isFavorite,
  devise,
  onToggleFavorite,
  onOpenDetails,
}: CoiffureCardProps) {
  return (
    <article className="group overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-sm">
      <div className="relative aspect-[4/3] bg-rose-50">
        <img src={coiffureImage(coiffure)} alt={coiffure.nom} className="h-full w-full object-cover" loading="lazy" />
        <button
          type="button"
          onClick={() => onToggleFavorite(coiffure.id)}
          className="absolute right-3 top-3 grid h-10 w-10 place-items-center rounded-full bg-white/90 text-[#f31976] shadow-sm"
          aria-label="Ajouter aux favoris"
        >
          <Heart className={`h-5 w-5 ${isFavorite ? 'fill-[#f31976]' : ''}`} />
        </button>
      </div>
      <div className="p-4">
        <p className="line-clamp-1 text-sm font-black text-slate-950 sm:text-base">{coiffure.nom}</p>
        <p className="mt-1 text-xs font-bold text-slate-500">{coiffure.categorie?.nom ?? 'Coiffure'}</p>
        <p className="mt-3 text-xs font-semibold text-slate-500">A partir de</p>
        <div className="mt-1 flex items-end justify-between gap-2">
          <p className="text-sm font-black text-[#f31976]">{formatCurrency(coiffure.prix_min, devise)}</p>
          <p className="text-xs font-bold text-slate-500">{formatDuration(coiffure.duree_min_minutes)}</p>
        </div>
        <button
          type="button"
          onClick={() => onOpenDetails(coiffure)}
          className="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-3 py-3 text-sm font-black text-white transition group-hover:bg-[#f31976]"
        >
          Details
          <ChevronRight className="h-4 w-4" />
        </button>
      </div>
    </article>
  )
}

export const CoiffureCard = memo(CoiffureCardBase)
