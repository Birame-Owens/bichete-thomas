import { useEffect, useMemo, useState } from 'react'
import { Search, ShoppingBag } from 'lucide-react'
import { getClientBoutique } from '../client.api'
import type { ClientBoutique } from '../client.types'
import { BoutiqueHeader } from './BoutiqueHeader'
import { ProduitCard } from './ProduitCard'

// Page publique /boutique : tous les produits visibles, filtres par
// categorie et recherche. Meme direction artistique que le site salon.
export function BoutiquePage() {
  const [boutique, setBoutique] = useState<ClientBoutique | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [categoryFilter, setCategoryFilter] = useState<number | null>(null)

  useEffect(() => {
    getClientBoutique()
      .then(setBoutique)
      .catch(() => setError('Impossible de charger la boutique. Réessayez plus tard.'))
      .finally(() => setLoading(false))
  }, [])

  const devise = boutique?.settings.devise ?? 'FCFA'
  const parents = useMemo(
    () => (boutique?.categories ?? []).filter((c) => !c.parent_id && c.produits_count > 0),
    [boutique],
  )

  const produits = useMemo(() => {
    let list = boutique?.produits ?? []
    if (categoryFilter) {
      const childIds = (boutique?.categories ?? [])
        .filter((c) => c.parent_id === categoryFilter)
        .map((c) => c.id)
      list = list.filter(
        (p) => p.categorie && (p.categorie.id === categoryFilter || childIds.includes(p.categorie.id)),
      )
    }
    if (search.trim()) {
      const q = search.trim().toLowerCase()
      list = list.filter(
        (p) => p.nom.toLowerCase().includes(q) || (p.description_courte ?? '').toLowerCase().includes(q),
      )
    }
    return list
  }, [boutique, categoryFilter, search])

  return (
    <div className="min-h-screen bg-[#faf5f8]">
      <BoutiqueHeader />

      <main className="mx-auto w-full max-w-[1440px] px-3 py-8 sm:px-5 lg:px-8">
        {/* Titre */}
        <div className="text-center">
          <h1 className="text-4xl font-light uppercase tracking-[0.24em] text-slate-950">La Boutique</h1>
          <p className="mt-3 text-sm font-semibold italic text-slate-500">
            Les produits du salon, disponibles à la commande.
          </p>
        </div>

        {/* Recherche + filtres */}
        <div className="mt-8 flex flex-col items-center gap-4">
          <label className="relative flex h-11 w-full max-w-md items-center">
            <Search className="pointer-events-none absolute left-4 h-4 w-4 text-slate-400" />
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Rechercher un produit"
              className="h-full w-full rounded-full border border-slate-200 bg-white pl-10 pr-4 text-sm font-bold outline-none transition focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
            />
          </label>

          {parents.length > 0 ? (
            <div className="flex flex-wrap justify-center gap-2">
              <button
                type="button"
                onClick={() => setCategoryFilter(null)}
                className={`h-9 px-4 text-[11px] font-black uppercase tracking-[0.14em] transition ${
                  categoryFilter === null
                    ? 'bg-[#f31976] text-white'
                    : 'bg-white text-slate-600 hover:text-[#f31976]'
                }`}
              >
                Tout
              </button>
              {parents.map((cat) => (
                <button
                  key={cat.id}
                  type="button"
                  onClick={() => setCategoryFilter(categoryFilter === cat.id ? null : cat.id)}
                  className={`h-9 px-4 text-[11px] font-black uppercase tracking-[0.14em] transition ${
                    categoryFilter === cat.id
                      ? 'bg-[#f31976] text-white'
                      : 'bg-white text-slate-600 hover:text-[#f31976]'
                  }`}
                >
                  {cat.nom}
                </button>
              ))}
            </div>
          ) : null}
        </div>

        {/* Grille */}
        {loading ? (
          <div className="mt-10 grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            {Array.from({ length: 10 }, (_, index) => (
              <div key={index} className="h-64 animate-pulse bg-white" />
            ))}
          </div>
        ) : error ? (
          <div className="mt-12 bg-white p-8 text-center text-sm font-bold text-rose-600">{error}</div>
        ) : produits.length === 0 ? (
          <div className="mt-12 bg-white p-10 text-center">
            <ShoppingBag className="mx-auto h-8 w-8 text-[#f31976]/40" />
            <p className="mt-3 text-sm font-bold text-slate-500">
              {search || categoryFilter ? 'Aucun produit ne correspond à votre recherche.' : 'La boutique arrive bientôt !'}
            </p>
          </div>
        ) : (
          <div className="mt-10 grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            {produits.map((produit) => (
              <ProduitCard
                key={produit.id}
                produit={produit}
                devise={devise}
                whatsapp={boutique?.settings.telephone_whatsapp}
              />
            ))}
          </div>
        )}
      </main>
    </div>
  )
}
