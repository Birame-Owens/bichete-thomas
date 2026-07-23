import { useState } from 'react'
import { Check, MessageCircle, ShoppingBag, ShoppingCart } from 'lucide-react'
import type { ClientProduit } from '../client.types'
import { addToPanier } from './panier'

// Carte produit publique — meme langage visuel que les CoiffureCard du site
// (photo carree, typographies uppercase, accent #f31976).
// - "Ajouter au panier" direct si le produit n a pas de variantes,
//   sinon "Choisir" ouvre la fiche pour selectionner couleur/taille.
// - Bouton WhatsApp : commande directe par message pre-rempli.
export function ProduitCard({ produit, devise, whatsapp }: {
  produit: ClientProduit
  devise: string
  whatsapp?: string
}) {
  const [added, setAdded] = useState(false)

  const handleAdd = (event: React.MouseEvent) => {
    event.preventDefault()
    event.stopPropagation()
    if (produit.a_variantes) {
      window.location.assign(`/boutique/${produit.slug}`)
      return
    }
    addToPanier({
      produitId: produit.id,
      slug: produit.slug,
      nom: produit.nom,
      image: produit.image,
      prix: produit.prix_actuel,
      couleur: null,
      taille: null,
    })
    setAdded(true)
    window.setTimeout(() => setAdded(false), 1600)
  }

  const whatsappUrl = whatsapp
    ? `https://wa.me/${whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent(
        `Bonjour Bichette Thomas ! Je suis intéressée par : ${produit.nom} (${Math.round(produit.prix_actuel).toLocaleString('fr-FR')} ${devise}). Lien : ${window.location.origin}/boutique/${produit.slug}`,
      )}`
    : null

  return (
    <a
      href={`/boutique/${produit.slug}`}
      className="group block bg-white transition-shadow duration-300 hover:shadow-[0_18px_40px_-24px_rgba(20,20,43,0.5)]"
    >
      <div className="relative aspect-square overflow-hidden bg-[#fff0f6]">
        {produit.image ? (
          <img
            src={produit.image}
            alt={produit.nom}
            loading="lazy"
            className="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-110"
          />
        ) : (
          <div className="grid h-full w-full place-items-center">
            <ShoppingBag className="h-10 w-10 text-[#f31976]/30" />
          </div>
        )}

        {/* Badges */}
        <div className="absolute left-2 top-2 flex flex-col gap-1">
          {produit.en_promo ? (
            <span className="bg-[#f31976] px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.14em] text-white">
              Promo
            </span>
          ) : null}
          {produit.est_nouveaute ? (
            <span className="bg-slate-950 px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.14em] text-white">
              Nouveau
            </span>
          ) : null}
        </div>

        {!produit.en_stock ? (
          <div className="absolute inset-x-0 bottom-0 bg-slate-950/80 py-1.5 text-center text-[10px] font-black uppercase tracking-[0.18em] text-white">
            Épuisé
          </div>
        ) : null}
      </div>

      <div className="px-2 py-3">
        <p className="truncate text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">
          {produit.categorie?.nom ?? 'Boutique'}
        </p>
        <h3 className="mt-1 truncate text-sm font-bold text-slate-950" title={produit.nom}>
          {produit.nom}
        </h3>
        <div className="mt-1.5 flex items-baseline gap-2">
          <span className="text-sm font-black text-[#f31976]">
            {Math.round(produit.prix_actuel).toLocaleString('fr-FR')} {devise}
          </span>
          {produit.en_promo ? (
            <span className="text-xs font-semibold text-slate-400 line-through">
              {Math.round(produit.prix).toLocaleString('fr-FR')}
            </span>
          ) : null}
        </div>

        {/* Actions : panier + WhatsApp */}
        {produit.en_stock ? (
          <div className="mt-2.5 flex gap-1.5">
            <button
              type="button"
              onClick={handleAdd}
              className={`inline-flex h-9 flex-1 items-center justify-center gap-1.5 text-[11px] font-black uppercase tracking-[0.1em] transition ${
                added
                  ? 'bg-emerald-500 text-white'
                  : 'bg-[#f31976] text-white hover:brightness-110'
              }`}
            >
              {added ? (
                <>
                  <Check className="h-3.5 w-3.5" />
                  Ajouté
                </>
              ) : (
                <>
                  <ShoppingCart className="h-3.5 w-3.5" />
                  {produit.a_variantes ? 'Choisir' : 'Ajouter'}
                </>
              )}
            </button>
            {whatsappUrl ? (
              <button
                type="button"
                onClick={(event) => {
                  event.preventDefault()
                  event.stopPropagation()
                  window.open(whatsappUrl, '_blank', 'noopener')
                }}
                title="Commander via WhatsApp"
                className="grid h-9 w-9 shrink-0 place-items-center bg-[#25d366] text-white transition hover:brightness-110"
              >
                <MessageCircle className="h-4 w-4" />
              </button>
            ) : null}
          </div>
        ) : null}
      </div>
    </a>
  )
}
