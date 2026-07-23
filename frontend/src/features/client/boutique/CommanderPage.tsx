import { useEffect, useMemo, useState } from 'react'
import { Loader2, Lock, MapPin, ShieldCheck, ShoppingBag, Store, Truck } from 'lucide-react'
import { createBoutiqueCommande, getClientBoutique } from '../client.api'
import { BoutiqueHeader } from './BoutiqueHeader'
import { clearPanier, panierTotal, usePanier } from './panier'

const FRAIS_LIVRAISON = 2000

type ModePaiement = 'wave' | 'orange_money' | 'livraison'

// Page /boutique/commander : coordonnees, livraison, paiement.
// Paiement en ligne = meme circuit NabooPay que les reservations.
export function CommanderPage() {
  const items = usePanier()
  const [devise, setDevise] = useState('FCFA')

  const [prenom, setPrenom] = useState('')
  const [nom, setNom] = useState('')
  const [telephone, setTelephone] = useState('')
  const [email, setEmail] = useState('')
  const [modeLivraison, setModeLivraison] = useState<'domicile' | 'boutique'>('domicile')
  const [adresse, setAdresse] = useState('')
  const [instructions, setInstructions] = useState('')
  const [modePaiement, setModePaiement] = useState<ModePaiement>('wave')

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getClientBoutique()
      .then((data) => setDevise(data.settings.devise))
      .catch(() => { /* devise par defaut */ })
  }, [])

  const sousTotal = panierTotal(items)
  const frais = modeLivraison === 'boutique' ? 0 : FRAIS_LIVRAISON
  const total = sousTotal + frais

  const formValide = useMemo(() =>
    prenom.trim() !== '' &&
    nom.trim() !== '' &&
    telephone.replace(/\D/g, '').length >= 9 &&
    (modeLivraison === 'boutique' || adresse.trim() !== ''),
  [prenom, nom, telephone, modeLivraison, adresse])

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    if (!formValide || items.length === 0) return
    setSubmitting(true)
    setError(null)

    try {
      const result = await createBoutiqueCommande({
        client: {
          prenom: prenom.trim(),
          nom: nom.trim(),
          telephone: telephone.trim(),
          email: email.trim() || null,
        },
        mode_livraison: modeLivraison,
        adresse_livraison: modeLivraison === 'domicile' ? adresse.trim() : null,
        instructions_livraison: instructions.trim() || null,
        mode_paiement: modePaiement,
        articles: items.map((item) => ({
          produit_id: item.produitId,
          quantite: item.quantite,
          couleur: item.couleur,
          taille: item.taille,
        })),
        success_url: `${window.location.origin}/boutique/commande-confirmee?paiement=naboopay_success`,
        cancel_url: `${window.location.origin}/boutique/commande-confirmee?paiement=naboopay_cancel`,
      })

      if (result.requires_redirect && result.checkout_url) {
        // Paiement en ligne : le panier sera vide au retour du paiement reussi.
        window.location.assign(result.checkout_url)
        return
      }

      // Paiement a la livraison : commande enregistree directement.
      clearPanier()
      window.location.assign(`/boutique/commande-confirmee?commande=${encodeURIComponent(result.numero_commande)}`)
    } catch (err: unknown) {
      const body = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data
      setError(
        body?.errors
          ? Object.values(body.errors).flat().join(' ')
          : body?.message ?? 'Impossible de valider la commande. Réessayez.',
      )
      setSubmitting(false)
    }
  }

  const inputCls = 'h-12 w-full border border-slate-200 bg-white px-4 text-sm font-bold text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10'

  if (items.length === 0) {
    return (
      <div className="min-h-screen bg-[#faf5f8]">
        <BoutiqueHeader backHref="/boutique" backLabel="Boutique" />
        <div className="grid min-h-[60vh] place-items-center px-4">
          <div className="bg-white p-12 text-center">
            <ShoppingBag className="mx-auto h-10 w-10 text-[#f31976]/30" />
            <p className="mt-4 text-sm font-bold text-slate-500">Votre panier est vide.</p>
            <a
              href="/boutique"
              className="mt-6 inline-flex h-12 items-center bg-[#f31976] px-8 text-xs font-black uppercase tracking-[0.16em] text-white"
            >
              Découvrir la boutique
            </a>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-[#faf5f8]">
      <BoutiqueHeader backHref="/boutique/panier" backLabel="Retour au panier" />

      <main className="mx-auto w-full max-w-[1000px] px-4 py-8 sm:px-6">
        <h1 className="text-center text-3xl font-light uppercase tracking-[0.24em] text-slate-950">Commander</h1>

        <form onSubmit={handleSubmit} className="mt-8 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
          {/* ── Colonne formulaire ── */}
          <div className="space-y-6">
            {/* Coordonnees */}
            <section className="bg-white p-5 sm:p-6">
              <h2 className="text-sm font-black uppercase tracking-[0.18em] text-slate-950">Vos coordonnées</h2>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <input value={prenom} onChange={(e) => setPrenom(e.target.value)} placeholder="Prénom *" className={inputCls} />
                <input value={nom} onChange={(e) => setNom(e.target.value)} placeholder="Nom *" className={inputCls} />
                <input value={telephone} onChange={(e) => setTelephone(e.target.value)} placeholder="Téléphone (ex: 77 123 45 67) *" inputMode="tel" className={inputCls} />
                <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="Email (optionnel)" type="email" className={inputCls} />
              </div>
            </section>

            {/* Livraison */}
            <section className="bg-white p-5 sm:p-6">
              <h2 className="text-sm font-black uppercase tracking-[0.18em] text-slate-950">Livraison</h2>
              <div className="mt-4 grid gap-2.5 sm:grid-cols-2">
                <button
                  type="button"
                  onClick={() => setModeLivraison('domicile')}
                  className={`flex items-center gap-3 border-2 p-4 text-left transition ${
                    modeLivraison === 'domicile' ? 'border-[#f31976] bg-[#fff0f6]' : 'border-slate-200 hover:border-[#f7d6e5]'
                  }`}
                >
                  <Truck className={`h-5 w-5 shrink-0 ${modeLivraison === 'domicile' ? 'text-[#f31976]' : 'text-slate-400'}`} />
                  <span>
                    <span className="block text-sm font-black text-slate-950">Livraison à Dakar</span>
                    <span className="block text-xs font-semibold text-slate-500">{FRAIS_LIVRAISON.toLocaleString('fr-FR')} {devise}</span>
                  </span>
                </button>
                <button
                  type="button"
                  onClick={() => setModeLivraison('boutique')}
                  className={`flex items-center gap-3 border-2 p-4 text-left transition ${
                    modeLivraison === 'boutique' ? 'border-[#f31976] bg-[#fff0f6]' : 'border-slate-200 hover:border-[#f7d6e5]'
                  }`}
                >
                  <Store className={`h-5 w-5 shrink-0 ${modeLivraison === 'boutique' ? 'text-[#f31976]' : 'text-slate-400'}`} />
                  <span>
                    <span className="block text-sm font-black text-slate-950">Retrait au salon</span>
                    <span className="block text-xs font-semibold text-emerald-600">Gratuit — lors de votre visite</span>
                  </span>
                </button>
              </div>

              {modeLivraison === 'domicile' ? (
                <div className="mt-3 space-y-3">
                  <div className="relative">
                    <MapPin className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                      value={adresse}
                      onChange={(e) => setAdresse(e.target.value)}
                      placeholder="Adresse de livraison (quartier, rue, repère) *"
                      className={`${inputCls} pl-10`}
                    />
                  </div>
                  <input
                    value={instructions}
                    onChange={(e) => setInstructions(e.target.value)}
                    placeholder="Instructions pour le livreur (optionnel)"
                    className={inputCls}
                  />
                </div>
              ) : null}
            </section>

            {/* Paiement */}
            <section className="bg-white p-5 sm:p-6">
              <h2 className="flex items-center gap-2 text-sm font-black uppercase tracking-[0.18em] text-slate-950">
                <Lock className="h-4 w-4 text-emerald-500" />
                Paiement sécurisé
              </h2>
              <div className="mt-4 space-y-2.5">
                {([
                  { value: 'wave', label: 'Wave', sub: 'Paiement mobile sécurisé', badge: '🌊' },
                  { value: 'orange_money', label: 'Orange Money', sub: 'Paiement mobile sécurisé', badge: '🟠' },
                  { value: 'livraison', label: 'Payer à la livraison', sub: 'En espèces à la réception', badge: '💵' },
                ] as { value: ModePaiement; label: string; sub: string; badge: string }[]).map((option) => (
                  <button
                    key={option.value}
                    type="button"
                    onClick={() => setModePaiement(option.value)}
                    className={`flex w-full items-center gap-3 border-2 p-4 text-left transition ${
                      modePaiement === option.value ? 'border-[#f31976] bg-[#fff0f6]' : 'border-slate-200 hover:border-[#f7d6e5]'
                    }`}
                  >
                    <span className="text-xl">{option.badge}</span>
                    <span className="flex-1">
                      <span className="block text-sm font-black text-slate-950">{option.label}</span>
                      <span className="block text-xs font-semibold text-slate-500">{option.sub}</span>
                    </span>
                    <span className={`grid h-5 w-5 place-items-center rounded-full border-2 ${
                      modePaiement === option.value ? 'border-[#f31976]' : 'border-slate-300'
                    }`}>
                      {modePaiement === option.value ? <span className="h-2.5 w-2.5 rounded-full bg-[#f31976]" /> : null}
                    </span>
                  </button>
                ))}
              </div>

              {/* Trust badges */}
              <div className="mt-4 flex flex-wrap items-center justify-center gap-x-5 gap-y-1.5 border-t border-[#f7d6e5] pt-4 text-[11px] font-bold text-slate-400">
                <span className="flex items-center gap-1.5">
                  <ShieldCheck className="h-4 w-4 text-emerald-500" />
                  Transactions cryptées
                </span>
                <span className="flex items-center gap-1.5">
                  <Lock className="h-4 w-4 text-emerald-500" />
                  Vos données protégées
                </span>
                <span className="flex items-center gap-1.5">
                  <Store className="h-4 w-4 text-emerald-500" />
                  Salon vérifié à Dakar
                </span>
              </div>
            </section>
          </div>

          {/* ── Colonne recapitulatif ── */}
          <div className="h-fit space-y-4 lg:sticky lg:top-20">
            <section className="bg-white p-5">
              <h2 className="text-sm font-black uppercase tracking-[0.18em] text-slate-950">Récapitulatif</h2>
              <div className="mt-4 space-y-2.5">
                {items.map((item) => (
                  <div key={`${item.produitId}-${item.couleur}-${item.taille}`} className="flex items-center gap-3">
                    <div className="h-12 w-12 shrink-0 overflow-hidden bg-[#fff0f6]">
                      {item.image ? <img src={item.image} alt="" className="h-full w-full object-cover" /> : null}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-xs font-bold text-slate-950">{item.nom}</p>
                      <p className="text-[11px] font-semibold text-slate-400">
                        {[item.couleur, item.taille].filter(Boolean).join(' · ')}{item.couleur || item.taille ? ' — ' : ''}x{item.quantite}
                      </p>
                    </div>
                    <span className="shrink-0 text-xs font-black text-slate-950">
                      {Math.round(item.prix * item.quantite).toLocaleString('fr-FR')}
                    </span>
                  </div>
                ))}
              </div>

              <div className="mt-4 space-y-1.5 border-t border-[#f7d6e5] pt-4 text-sm font-bold">
                <div className="flex justify-between text-slate-500">
                  <span>Sous-total</span>
                  <span>{Math.round(sousTotal).toLocaleString('fr-FR')} {devise}</span>
                </div>
                <div className="flex justify-between text-slate-500">
                  <span>Livraison</span>
                  <span>{frais === 0 ? 'Gratuit' : `${frais.toLocaleString('fr-FR')} ${devise}`}</span>
                </div>
                <div className="flex justify-between pt-1.5 text-base font-black text-slate-950">
                  <span>Total</span>
                  <span className="text-[#f31976]">{Math.round(total).toLocaleString('fr-FR')} {devise}</span>
                </div>
              </div>
            </section>

            {error ? (
              <div className="bg-rose-50 p-4 text-sm font-bold text-rose-600">{error}</div>
            ) : null}

            <button
              type="submit"
              disabled={!formValide || submitting}
              className={`inline-flex w-full items-center justify-center gap-2.5 px-6 py-4 text-sm font-black uppercase tracking-[0.16em] transition ${
                !formValide || submitting
                  ? 'cursor-not-allowed bg-slate-200 text-slate-400'
                  : 'bg-[#f31976] text-white hover:brightness-110'
              }`}
            >
              {submitting ? <Loader2 className="h-5 w-5 animate-spin" /> : <Lock className="h-4 w-4" />}
              {submitting
                ? 'Traitement…'
                : modePaiement === 'livraison'
                  ? 'Confirmer la commande'
                  : `Payer ${Math.round(total).toLocaleString('fr-FR')} ${devise}`}
            </button>
          </div>
        </form>
      </main>
    </div>
  )
}
