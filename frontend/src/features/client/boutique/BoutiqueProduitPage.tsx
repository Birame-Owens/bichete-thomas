import { useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { ArrowLeft, Check, MessageCircle, ShieldCheck, ShoppingBag, ShoppingCart, Star, Truck } from 'lucide-react'
import { getClientBoutique, getClientProduitDetail } from '../client.api'
import type { ClientProduitDetail } from '../client.types'
import { COLOR_PALETTE, LIGHT_HEXES } from '../../../lib/colorPalette'
import { BoutiqueHeader } from './BoutiqueHeader'
import { addToPanier } from './panier'

// Fiche produit publique /boutique/:slug — galerie par couleur, pastilles de
// couleurs reelles, ajout panier et commande WhatsApp.
export function BoutiqueProduitPage() {
  const { slug } = useParams<{ slug: string }>()
  const [produit, setProduit] = useState<ClientProduitDetail | null>(null)
  const [whatsapp, setWhatsapp] = useState<string>('')
  const [devise, setDevise] = useState('FCFA')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const [selectedCouleur, setSelectedCouleur] = useState<string | null>(null)
  const [selectedTaille, setSelectedTaille] = useState<string | null>(null)
  const [activeImage, setActiveImage] = useState<string | null>(null)
  const [added, setAdded] = useState(false)

  useEffect(() => {
    if (!slug) return
    Promise.all([
      getClientProduitDetail(slug),
      getClientBoutique().catch(() => null),
    ])
      .then(([detail, boutique]) => {
        setProduit(detail)
        setActiveImage(detail.image ?? detail.images[0]?.url ?? null)
        const couleurs = Object.keys(detail.couleur_tailles ?? {})
        if (couleurs.length > 0) setSelectedCouleur(couleurs[0])
        if (boutique) {
          setWhatsapp(boutique.settings.telephone_whatsapp)
          setDevise(boutique.settings.devise)
        }
      })
      .catch(() => setError('Produit introuvable.'))
      .finally(() => setLoading(false))
  }, [slug])

  const couleurs = useMemo(() => Object.keys(produit?.couleur_tailles ?? {}), [produit])
  const isParfum = produit?.type_variante === 'parfum'
  const axe2Label = isParfum ? 'Contenance' : produit?.type_variante === 'chaussure' ? 'Pointure' : 'Taille'

  const tailles = useMemo(() => {
    if (!produit || !selectedCouleur) return []
    return produit.couleur_tailles?.[selectedCouleur] ?? []
  }, [produit, selectedCouleur])

  const stockFor = (couleur: string, taille: string) =>
    produit?.couleur_tailles_stock?.[couleur]?.[taille] ?? null

  const hexFor = (name: string) => COLOR_PALETTE.find((c) => c.name === name)?.hex ?? null

  // Galerie : photos de la couleur choisie en priorite, sinon toutes.
  const galleryImages = useMemo(() => {
    const images = produit?.images ?? []
    if (!selectedCouleur) return images
    const filtered = images.filter((img) => img.couleur_associee === selectedCouleur)
    return filtered.length > 0 ? filtered : images
  }, [produit, selectedCouleur])

  useEffect(() => {
    if (galleryImages.length > 0) setActiveImage(galleryImages[0].url)
    else if (produit?.image) setActiveImage(produit.image)
    setSelectedTaille(null)
  }, [selectedCouleur]) // eslint-disable-line react-hooks/exhaustive-deps

  const varianteRequise = couleurs.length > 0
  const varianteChoisie = !varianteRequise || (selectedCouleur && (tailles.length === 0 || selectedTaille))

  const handleAddToCart = () => {
    if (!produit || !varianteChoisie) return
    addToPanier({
      produitId: produit.id,
      slug: produit.slug,
      nom: produit.nom,
      image: galleryImages[0]?.url ?? produit.image,
      prix: produit.prix_actuel,
      couleur: selectedCouleur,
      taille: selectedTaille,
    })
    setAdded(true)
    window.setTimeout(() => setAdded(false), 1800)
  }

  const commanderUrl = useMemo(() => {
    if (!produit || !whatsapp) return null
    const variante = [
      selectedCouleur ? `${isParfum ? 'Senteur' : 'Couleur'} : ${selectedCouleur}` : null,
      selectedTaille ? `${axe2Label} : ${selectedTaille}` : null,
    ].filter(Boolean).join(', ')
    const message = [
      `Bonjour Bichette Thomas ! Je souhaite commander :`,
      `• ${produit.nom}${variante ? ` (${variante})` : ''}`,
      `• Prix : ${Math.round(produit.prix_actuel).toLocaleString('fr-FR')} ${devise}`,
      `Lien : ${window.location.href}`,
    ].join('\n')
    return `https://wa.me/${whatsapp.replace(/\D/g, '')}?text=${encodeURIComponent(message)}`
  }, [produit, whatsapp, selectedCouleur, selectedTaille, axe2Label, devise, isParfum])

  if (loading) {
    return (
      <div className="min-h-screen bg-[#faf5f8]">
        <BoutiqueHeader backHref="/boutique" backLabel="Boutique" />
        <div className="mx-auto max-w-[1100px] px-4 py-10">
          <div className="grid gap-8 lg:grid-cols-2">
            <div className="aspect-square animate-pulse bg-white" />
            <div className="space-y-4">
              <div className="h-8 w-2/3 animate-pulse bg-white" />
              <div className="h-6 w-1/3 animate-pulse bg-white" />
              <div className="h-32 animate-pulse bg-white" />
            </div>
          </div>
        </div>
      </div>
    )
  }

  if (error || !produit) {
    return (
      <div className="min-h-screen bg-[#faf5f8]">
        <BoutiqueHeader backHref="/boutique" backLabel="Boutique" />
        <div className="grid min-h-[60vh] place-items-center px-4">
          <div className="bg-white p-10 text-center">
            <ShoppingBag className="mx-auto h-8 w-8 text-[#f31976]/40" />
            <p className="mt-3 text-sm font-bold text-slate-600">{error ?? 'Produit introuvable.'}</p>
            <a
              href="/boutique"
              className="mt-5 inline-flex h-11 items-center gap-2 bg-[#f31976] px-6 text-xs font-black uppercase tracking-[0.16em] text-white"
            >
              <ArrowLeft className="h-4 w-4" />
              Retour à la boutique
            </a>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-[#faf5f8]">
      <BoutiqueHeader backHref="/boutique" backLabel="Boutique" />

      <main className="mx-auto w-full max-w-[1100px] px-4 py-8 sm:px-6">
        <div className="grid gap-8 lg:grid-cols-2">
          {/* ── Galerie ── */}
          <div>
            <div className="relative aspect-square overflow-hidden bg-white">
              {activeImage ? (
                <img src={activeImage} alt={produit.nom} className="h-full w-full object-cover" />
              ) : (
                <div className="grid h-full w-full place-items-center">
                  <ShoppingBag className="h-12 w-12 text-[#f31976]/30" />
                </div>
              )}
              {produit.en_promo ? (
                <span className="absolute left-3 top-3 bg-[#f31976] px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-white">
                  Promo
                </span>
              ) : null}
            </div>

            {galleryImages.length > 1 ? (
              <div className="mt-3 flex gap-2 overflow-x-auto pb-1">
                {galleryImages.map((image) => (
                  <button
                    key={image.id}
                    type="button"
                    onClick={() => setActiveImage(image.url)}
                    className={`h-16 w-16 shrink-0 overflow-hidden border-2 transition ${
                      activeImage === image.url ? 'border-[#f31976]' : 'border-transparent hover:border-[#f7d6e5]'
                    }`}
                  >
                    <img src={image.url_miniature ?? image.url} alt="" className="h-full w-full object-cover" />
                  </button>
                ))}
              </div>
            ) : null}
          </div>

          {/* ── Infos + variante + CTA ── */}
          <div>
            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">
              {produit.categorie?.nom ?? 'Boutique'}
            </p>
            <h1 className="mt-2 text-3xl font-light uppercase tracking-[0.1em] text-slate-950">{produit.nom}</h1>

            {produit.nombre_avis > 0 && produit.note_moyenne !== null ? (
              <div className="mt-2 flex items-center gap-1.5 text-sm font-bold text-slate-600">
                <Star className="h-4 w-4 fill-amber-400 text-amber-400" />
                {produit.note_moyenne.toFixed(1)} · {produit.nombre_avis} avis
              </div>
            ) : null}

            <div className="mt-4 flex items-baseline gap-3">
              <span className="text-3xl font-black text-[#f31976]">
                {Math.round(produit.prix_actuel).toLocaleString('fr-FR')} {devise}
              </span>
              {produit.en_promo ? (
                <span className="text-lg font-semibold text-slate-400 line-through">
                  {Math.round(produit.prix).toLocaleString('fr-FR')}
                </span>
              ) : null}
            </div>

            {produit.description_courte ? (
              <p className="mt-3 text-sm font-semibold italic text-slate-500">{produit.description_courte}</p>
            ) : null}

            {/* Couleurs (pastilles reelles) / senteurs (texte) */}
            {couleurs.length > 0 ? (
              <div className="mt-6">
                <p className="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                  {isParfum ? 'Senteur' : 'Couleur'}
                  {selectedCouleur ? <span className="ml-2 text-[#f31976]">{selectedCouleur}</span> : null}
                </p>
                <div className="mt-2.5 flex flex-wrap items-center gap-2.5">
                  {couleurs.map((couleur) => {
                    const hex = isParfum ? null : hexFor(couleur)
                    const selected = selectedCouleur === couleur

                    // Pastille de couleur reelle quand on connait le hex,
                    // sinon (senteur ou couleur inconnue) chip texte.
                    if (hex) {
                      return (
                        <button
                          key={couleur}
                          type="button"
                          title={couleur}
                          onClick={() => setSelectedCouleur(couleur)}
                          className={`grid h-10 w-10 place-items-center rounded-full border-2 transition-transform hover:scale-110 ${
                            selected ? 'border-[#f31976] scale-110 shadow-md' : 'border-slate-200'
                          }`}
                          style={{ backgroundColor: hex }}
                        >
                          {selected ? (
                            <Check
                              className="h-4 w-4 drop-shadow"
                              strokeWidth={3.5}
                              style={{ color: LIGHT_HEXES.includes(hex) ? '#1A1A1A' : '#ffffff' }}
                            />
                          ) : null}
                        </button>
                      )
                    }

                    return (
                      <button
                        key={couleur}
                        type="button"
                        onClick={() => setSelectedCouleur(couleur)}
                        className={`h-10 px-4 text-xs font-bold transition ${
                          selected ? 'bg-[#f31976] text-white' : 'bg-white text-slate-700 hover:text-[#f31976]'
                        }`}
                      >
                        {isParfum ? '🌸 ' : ''}{couleur}
                      </button>
                    )
                  })}
                </div>
              </div>
            ) : null}

            {/* Tailles / contenances */}
            {tailles.length > 0 ? (
              <div className="mt-5">
                <p className="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                  {axe2Label}
                  {selectedTaille ? <span className="ml-2 text-[#f31976]">{selectedTaille}</span> : null}
                </p>
                <div className="mt-2.5 flex flex-wrap gap-2">
                  {tailles.map((taille) => {
                    const stock = selectedCouleur ? stockFor(selectedCouleur, taille) : null
                    const epuise = stock !== null && stock <= 0
                    return (
                      <button
                        key={taille}
                        type="button"
                        disabled={epuise}
                        onClick={() => setSelectedTaille(taille)}
                        className={`h-9 min-w-[44px] px-3 text-xs font-bold transition ${
                          epuise
                            ? 'cursor-not-allowed bg-white text-slate-300 line-through'
                            : selectedTaille === taille
                              ? 'bg-[#f31976] text-white'
                              : 'bg-white text-slate-700 hover:text-[#f31976]'
                        }`}
                      >
                        {taille}
                      </button>
                    )
                  })}
                </div>
              </div>
            ) : null}

            {/* Livraison / sur mesure */}
            <div className="mt-6 flex items-center gap-2 text-xs font-bold text-slate-500">
              <Truck className="h-4 w-4 text-[#f31976]" />
              {produit.fait_sur_mesure && produit.delai_production_jours
                ? `Confection sur commande — délai ${produit.delai_production_jours} jour${produit.delai_production_jours > 1 ? 's' : ''}`
                : 'Livraison à Dakar ou retrait gratuit au salon'}
            </div>

            {/* CTA : panier + WhatsApp */}
            <div className="mt-7 space-y-2.5">
              {!produit.en_stock ? (
                <div className="bg-white p-4 text-center text-sm font-black uppercase tracking-[0.14em] text-slate-400">
                  Produit épuisé
                </div>
              ) : (
                <>
                  <button
                    type="button"
                    onClick={handleAddToCart}
                    disabled={!varianteChoisie}
                    className={`inline-flex w-full items-center justify-center gap-2.5 px-6 py-4 text-sm font-black uppercase tracking-[0.16em] transition ${
                      !varianteChoisie
                        ? 'cursor-not-allowed bg-slate-200 text-slate-400'
                        : added
                          ? 'bg-emerald-500 text-white'
                          : 'bg-[#f31976] text-white hover:brightness-110'
                    }`}
                  >
                    {added ? <Check className="h-5 w-5" /> : <ShoppingCart className="h-5 w-5" />}
                    {added ? 'Ajouté au panier !' : 'Ajouter au panier'}
                  </button>

                  {added ? (
                    <a
                      href="/boutique/panier"
                      className="inline-flex w-full items-center justify-center gap-2 border border-[#f31976] px-6 py-3 text-xs font-black uppercase tracking-[0.16em] text-[#f31976] transition hover:bg-[#fff0f6]"
                    >
                      Voir le panier et payer
                    </a>
                  ) : null}

                  {commanderUrl ? (
                    <a
                      href={varianteChoisie ? commanderUrl : undefined}
                      target="_blank"
                      rel="noreferrer"
                      onClick={(event) => { if (!varianteChoisie) event.preventDefault() }}
                      className={`inline-flex w-full items-center justify-center gap-2.5 px-6 py-3.5 text-xs font-black uppercase tracking-[0.16em] transition ${
                        varianteChoisie
                          ? 'bg-[#25d366] text-white hover:brightness-105'
                          : 'cursor-not-allowed bg-slate-200 text-slate-400'
                      }`}
                    >
                      <MessageCircle className="h-4 w-4" />
                      Ou commander sur WhatsApp
                    </a>
                  ) : null}
                </>
              )}

              {varianteRequise && !varianteChoisie ? (
                <p className="text-center text-xs font-bold text-slate-400">
                  Choisissez {isParfum ? 'une senteur' : 'une couleur'}{tailles.length > 0 ? ` et une ${axe2Label.toLowerCase()}` : ''} pour continuer.
                </p>
              ) : null}

              <div className="flex items-center justify-center gap-2 pt-1 text-[11px] font-bold text-slate-400">
                <ShieldCheck className="h-4 w-4 text-emerald-500" />
                Paiement 100% sécurisé — Wave · Orange Money
              </div>
            </div>
          </div>
        </div>

        {/* ── Description ── */}
        {produit.description ? (
          <section className="mt-12 bg-white p-6 sm:p-8">
            <h2 className="text-lg font-light uppercase tracking-[0.2em] text-slate-950">Description</h2>
            <p className="mt-4 whitespace-pre-line text-sm font-semibold leading-relaxed text-slate-600">
              {produit.description}
            </p>
          </section>
        ) : null}

        {/* ── Avis ── */}
        {produit.avis.length > 0 ? (
          <section className="mt-8 bg-white p-6 sm:p-8">
            <h2 className="text-lg font-light uppercase tracking-[0.2em] text-slate-950">Avis clientes</h2>
            <div className="mt-4 space-y-4">
              {produit.avis.map((avis) => (
                <div key={avis.id} className="border-b border-[#f7d6e5] pb-4 last:border-0 last:pb-0">
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-black text-slate-950">{avis.client_nom}</span>
                    <span className="flex items-center gap-0.5 text-amber-400">
                      {Array.from({ length: 5 }, (_, i) => (
                        <Star key={i} className={`h-3.5 w-3.5 ${i < avis.note ? 'fill-amber-400' : 'text-slate-200'}`} />
                      ))}
                    </span>
                    <span className="ml-auto text-xs font-semibold text-slate-400">{avis.date}</span>
                  </div>
                  <p className="mt-1.5 text-sm font-semibold text-slate-600">{avis.commentaire}</p>
                </div>
              ))}
            </div>
          </section>
        ) : null}
      </main>
    </div>
  )
}
