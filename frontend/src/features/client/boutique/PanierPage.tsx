import { useEffect, useState } from 'react'
import { Minus, Plus, ShieldCheck, ShoppingBag, Trash2 } from 'lucide-react'
import { getClientBoutique } from '../client.api'
import { BoutiqueHeader } from './BoutiqueHeader'
import { panierTotal, removeFromPanier, updateQuantite, usePanier } from './panier'

// Page /boutique/panier : recapitulatif des articles, quantites, total,
// et passage a la commande.
export function PanierPage() {
  const items = usePanier()
  const [devise, setDevise] = useState('FCFA')

  useEffect(() => {
    getClientBoutique()
      .then((data) => setDevise(data.settings.devise))
      .catch(() => { /* devise par defaut */ })
  }, [])

  const total = panierTotal(items)

  return (
    <div className="min-h-screen bg-[#faf5f8]">
      <BoutiqueHeader backHref="/boutique" backLabel="Continuer mes achats" />

      <main className="mx-auto w-full max-w-[900px] px-4 py-8 sm:px-6">
        <h1 className="text-center text-3xl font-light uppercase tracking-[0.24em] text-slate-950">Mon panier</h1>

        {items.length === 0 ? (
          <div className="mt-10 bg-white p-12 text-center">
            <ShoppingBag className="mx-auto h-10 w-10 text-[#f31976]/30" />
            <p className="mt-4 text-sm font-bold text-slate-500">Votre panier est vide.</p>
            <a
              href="/boutique"
              className="mt-6 inline-flex h-12 items-center bg-[#f31976] px-8 text-xs font-black uppercase tracking-[0.16em] text-white transition hover:brightness-110"
            >
              Découvrir la boutique
            </a>
          </div>
        ) : (
          <>
            <div className="mt-8 space-y-3">
              {items.map((item) => (
                <div
                  key={`${item.produitId}-${item.couleur}-${item.taille}`}
                  className="flex items-center gap-4 bg-white p-4"
                >
                  <a href={`/boutique/${item.slug}`} className="h-20 w-20 shrink-0 overflow-hidden bg-[#fff0f6]">
                    {item.image ? (
                      <img src={item.image} alt={item.nom} className="h-full w-full object-cover" />
                    ) : (
                      <div className="grid h-full w-full place-items-center">
                        <ShoppingBag className="h-6 w-6 text-[#f31976]/30" />
                      </div>
                    )}
                  </a>

                  <div className="min-w-0 flex-1">
                    <a href={`/boutique/${item.slug}`} className="block truncate text-sm font-bold text-slate-950 hover:text-[#f31976]">
                      {item.nom}
                    </a>
                    {(item.couleur || item.taille) ? (
                      <p className="mt-0.5 text-xs font-semibold text-slate-500">
                        {[item.couleur, item.taille].filter(Boolean).join(' · ')}
                      </p>
                    ) : null}
                    <p className="mt-1 text-sm font-black text-[#f31976]">
                      {Math.round(item.prix).toLocaleString('fr-FR')} {devise}
                    </p>
                  </div>

                  {/* Quantite */}
                  <div className="flex items-center gap-1">
                    <button
                      type="button"
                      onClick={() => updateQuantite(item.produitId, item.couleur, item.taille, item.quantite - 1)}
                      className="grid h-8 w-8 place-items-center bg-[#fff0f6] text-[#f31976] transition hover:brightness-95"
                    >
                      <Minus className="h-3.5 w-3.5" />
                    </button>
                    <span className="w-8 text-center text-sm font-black text-slate-950">{item.quantite}</span>
                    <button
                      type="button"
                      onClick={() => updateQuantite(item.produitId, item.couleur, item.taille, item.quantite + 1)}
                      className="grid h-8 w-8 place-items-center bg-[#fff0f6] text-[#f31976] transition hover:brightness-95"
                    >
                      <Plus className="h-3.5 w-3.5" />
                    </button>
                  </div>

                  <button
                    type="button"
                    onClick={() => removeFromPanier(item.produitId, item.couleur, item.taille)}
                    title="Retirer du panier"
                    className="grid h-9 w-9 shrink-0 place-items-center text-rose-400 transition hover:bg-rose-50"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              ))}
            </div>

            {/* Total + CTA */}
            <div className="mt-6 bg-white p-5">
              <div className="flex items-center justify-between text-sm font-bold text-slate-500">
                <span>Sous-total</span>
                <span className="text-slate-950">{Math.round(total).toLocaleString('fr-FR')} {devise}</span>
              </div>
              <p className="mt-1.5 text-xs font-semibold text-slate-400">
                Livraison à Dakar : 2 000 {devise} — offerte en retrait au salon. Calculée à l'étape suivante.
              </p>

              <a
                href="/boutique/commander"
                className="mt-5 inline-flex w-full items-center justify-center bg-[#f31976] px-6 py-4 text-sm font-black uppercase tracking-[0.16em] text-white transition hover:brightness-110"
              >
                Passer la commande
              </a>

              <div className="mt-3 flex items-center justify-center gap-2 text-[11px] font-bold text-slate-400">
                <ShieldCheck className="h-4 w-4 text-emerald-500" />
                Paiement 100% sécurisé — Wave · Orange Money · à la livraison
              </div>
            </div>
          </>
        )}
      </main>
    </div>
  )
}
