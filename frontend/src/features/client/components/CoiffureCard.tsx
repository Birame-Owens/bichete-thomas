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
    <article className="group cursor-pointer bg-transparent" onClick={() => onOpenDetails(coiffure)}>
      <div className="relative aspect-[4/5] overflow-hidden bg-[#fff0f6]">
        <img
          src={coiffureImage(coiffure)}
          alt={coiffure.nom}
          className="h-full w-full object-cover transition duration-700 group-hover:scale-105"
          loading="lazy"
        />
        <button
          type="button"
          onClick={(event) => {
            event.stopPropagation()
            onToggleFavorite(coiffure.id)
          }}
          className="absolute right-3 top-3 grid h-10 w-10 place-items-center rounded-full bg-white/90 text-[#f31976] shadow-sm"
          aria-label="Ajouter aux favoris"
        >
          <Heart className={`h-5 w-5 ${isFavorite ? 'fill-[#f31976]' : ''}`} />
        </button>
        <div className="absolute left-2 top-2 flex flex-col gap-1">
          {coiffure.est_populaire ? (
            <span className="bg-[#f31976] px-2 py-1 text-[9px] font-black uppercase tracking-[0.18em] text-white">Populaire</span>
          ) : coiffure.est_nouveaute ? (
            <span className="bg-white px-2 py-1 text-[9px] font-black uppercase tracking-[0.18em] text-slate-950">Accueil</span>
          ) : null}
        </div>
        <button
          type="button"
          onClick={(event) => {
            event.stopPropagation()
            onOpenDetails(coiffure)
          }}
          className="absolute inset-x-0 bottom-0 hidden translate-y-full bg-[#f31976] py-3 text-[10px] font-black uppercase tracking-[0.22em] text-white transition group-hover:translate-y-0 md:block"
        >
          Details
        </button>
      </div>
      <div className="pt-3 text-center">
        <p className="line-clamp-1 text-xs font-black uppercase tracking-[0.14em] text-slate-950">{coiffure.nom}</p>
        <p className="mt-1 text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">{coiffure.categorie?.nom ?? 'Coiffure'}</p>
        <div className="mt-2 flex items-center justify-center gap-3 text-xs">
          <p className="font-black text-[#f31976]">{formatCurrency(coiffure.prix_min, devise)}</p>
          <span className="h-1 w-1 rounded-full bg-slate-300" />
          <p className="font-bold text-slate-500">{formatDuration(coiffure.duree_min_minutes)}</p>
        </div>
        <button
          type="button"
          onClick={(event) => {
            event.stopPropagation()
            onOpenDetails(coiffure)
          }}
          className="mt-3 inline-flex min-h-9 items-center justify-center gap-1 bg-[#f31976] px-4 text-[10px] font-black uppercase tracking-[0.2em] text-white md:hidden"
        >
          Voir
          <ChevronRight className="h-4 w-4" />
        </button>
      </div>
    </article>
  )
}

export const CoiffureCard = memo(CoiffureCardBase)
