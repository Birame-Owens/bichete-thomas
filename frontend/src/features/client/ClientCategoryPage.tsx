import { Bell, Check, CheckCircle, Home, Loader2, MapPin, MessageCircle, Phone, Scissors, Search, User, Users } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { confirmNaboopayReturn, getClientCatalogue } from './client.api'
import { useSeoPage } from '../../hooks/useSeoPage'
import { formatCurrency, formatShortDate } from './client.helpers'
import { CoiffureCard } from './components/CoiffureCard'
import Reveal from './components/Reveal'
import ReservationModal from './components/ReservationModal'
import type { ClientCatalogue, ClientCoiffure, ClientPaymentWithRelations } from './client.types'

const emptyFavorites: number[] = []

function ClientCategoryPage() {
  const { categoryId } = useParams()
  useSeoPage(categoryId ? `categorie-${categoryId}` : 'categories')
  const navigate = useNavigate()
  const parsedCategoryId = categoryId ? Number(categoryId) : null
  const [catalogue, setCatalogue] = useState<ClientCatalogue | null>(null)
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selectedCoiffure, setSelectedCoiffure] = useState<ClientCoiffure | null>(null)
  const [submitMessage, setSubmitMessage] = useState<string | null>(null)
  const [paymentConfirmation, setPaymentConfirmation] = useState<ClientPaymentWithRelations | null>(null)
  const [paymentConfirming, setPaymentConfirming] = useState(false)

  const settings = catalogue?.settings

  useEffect(() => {
    let ignore = false

    getClientCatalogue()
      .then((data) => {
        if (!ignore) {
          setCatalogue(data)
          setError(null)
        }
      })
      .catch(() => {
        if (!ignore) {
          setError('Le catalogue est indisponible pour le moment.')
        }
      })
      .finally(() => {
        if (!ignore) {
          setLoading(false)
        }
      })

    return () => {
      ignore = true
    }
  }, [])

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const paymentStatus = params.get('paiement')

    if (paymentStatus === 'naboopay_cancel') {
      setSubmitMessage('Paiement NabooPay annule. Le creneau ne sera pas confirme.')
      window.history.replaceState({}, '', window.location.pathname)
      return
    }

    if (paymentStatus !== 'naboopay_success') {
      return
    }

    const paymentId = params.get('paiement_id')
    const signature = params.get('signature')

    if (!paymentId || !signature) {
      setSubmitMessage('Retour NabooPay recu. La confirmation sera appliquee par webhook.')
      window.history.replaceState({}, '', window.location.pathname)
      return
    }

    setPaymentConfirming(true)
    confirmNaboopayReturn(paymentId, signature)
      .then((response) => {
        setPaymentConfirmation(response.data)
        window.history.replaceState({}, '', window.location.pathname)
      })
      .catch(() => {
        setSubmitMessage('Retour NabooPay recu. La confirmation sera appliquee par webhook.')
        window.history.replaceState({}, '', window.location.pathname)
      })
      .finally(() => setPaymentConfirming(false))
  }, [])

  const categories = catalogue?.categories ?? []
  const devise = catalogue?.settings.devise ?? 'FCFA'
  const activeCategory = categories.find((category) => category.id === parsedCategoryId)

  const coiffures = useMemo(() => {
    const list = catalogue?.coiffures ?? []
    const query = search.trim().toLowerCase()

    const categoryList = parsedCategoryId
      ? list.filter((coiffure) => coiffure.categorie?.id === parsedCategoryId)
      : list

    if (query === '') {
      return categoryList
    }

    return categoryList.filter((coiffure) =>
      coiffure.nom.toLowerCase().includes(query)
      || (coiffure.description ?? '').toLowerCase().includes(query)
      || (coiffure.categorie?.nom ?? '').toLowerCase().includes(query),
    )
  }, [catalogue?.coiffures, parsedCategoryId, search])

  // Ouvre le modal de reservation. Le composant ReservationModal recupere
  // lui-meme les details complets de la coiffure.
  function openDetails(coiffure: ClientCoiffure) {
    setSelectedCoiffure(coiffure)
  }

  return (
    <div className="min-h-screen bg-[#fdfafd] text-slate-950">

      {paymentConfirming && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
          <div className="flex flex-col items-center gap-4 rounded-3xl bg-white px-12 py-10 shadow-2xl">
            <Loader2 className="h-10 w-10 animate-spin text-[#f31976]" />
            <p className="text-sm font-black text-slate-700">Vérification du paiement...</p>
          </div>
        </div>
      )}

      {paymentConfirmation !== null && (
        <div className="bt-overlay-in fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
          <div className="bt-sheet-in w-full max-w-sm rounded-3xl bg-white p-7 shadow-2xl">

            <div className="flex justify-center">
              <div className="bt-success-pop flex h-20 w-20 items-center justify-center rounded-full bg-[#fff0f7]">
                <CheckCircle className="h-10 w-10 text-[#f31976]" />
              </div>
            </div>

            <p className="bt-animate-fade-up mt-5 text-center text-[11px] font-black uppercase tracking-[0.15em] text-[#f31976]" style={{ animationDelay: '0.15s' }}>
              Paiement confirmé
            </p>
            <h2 className="mt-1 text-center font-display text-2xl font-black text-slate-950">
              Merci{' '}
              {paymentConfirmation.client?.prenom
                ?? paymentConfirmation.reservation?.client?.prenom
                ?? ''}{' '}
              {paymentConfirmation.client?.nom
                ?? paymentConfirmation.reservation?.client?.nom
                ?? ''}
            </h2>

            <div className="mt-5 rounded-2xl bg-[#fff8fb] p-4 text-center">
              <p className="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">Numéro de reçu</p>
              <p className="mt-1 font-mono text-base font-black text-slate-950">{paymentConfirmation.numero_recu}</p>
            </div>

            <div className="mt-3 grid grid-cols-2 gap-3">
              <div className="rounded-2xl bg-[#fff8fb] p-3">
                <p className="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Montant payé</p>
                <p className="mt-1 text-sm font-black text-slate-950">
                  {formatCurrency(Number(paymentConfirmation.montant), paymentConfirmation.devise)}
                </p>
              </div>
              <div className="rounded-2xl bg-[#fff8fb] p-3">
                <p className="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Mode</p>
                <p className="mt-1 text-sm font-black text-slate-950">
                  {paymentConfirmation.mode_paiement === 'wave'
                    ? 'Wave'
                    : paymentConfirmation.mode_paiement === 'orange_money'
                      ? 'Orange Money'
                      : 'Carte bancaire'}
                </p>
              </div>
              {Number(paymentConfirmation.reservation?.montant_restant ?? 0) > 0 && (
                <div className="rounded-2xl bg-[#fff8fb] p-3">
                  <p className="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Reste à payer</p>
                  <p className="mt-1 text-sm font-black text-[#f31976]">
                    {formatCurrency(Number(paymentConfirmation.reservation?.montant_restant), paymentConfirmation.devise)}
                  </p>
                </div>
              )}
              {paymentConfirmation.reservation?.date_reservation && (
                <div className="rounded-2xl bg-[#fff8fb] p-3">
                  <p className="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Réservation</p>
                  <p className="mt-1 text-sm font-black text-slate-950">
                    {formatShortDate(paymentConfirmation.reservation.date_reservation)}
                    {paymentConfirmation.reservation.heure_debut
                      ? ` à ${paymentConfirmation.reservation.heure_debut.slice(0, 5)}`
                      : ''}
                  </p>
                </div>
              )}
            </div>

            <div className="mt-3 flex items-start gap-3 rounded-2xl border border-[#f7d6e5] bg-[#fff8fb] p-3.5">
              <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f31976]/10">
                <Phone className="h-4 w-4 text-[#f31976]" />
              </div>
              <div>
                <p className="text-xs font-black text-slate-950">Confirmation WhatsApp envoyée</p>
                <p className="mt-0.5 text-xs font-semibold leading-5 text-slate-500">
                  Un message de confirmation a été envoyé sur votre WhatsApp.
                </p>
              </div>
            </div>

            {(paymentConfirmation.client?.email ?? paymentConfirmation.reservation?.client?.email) && (
              <div className="mt-3 flex items-start gap-3 rounded-2xl border border-[#f7d6e5] bg-[#fff8fb] p-3.5">
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#f31976]/10">
                  <Check className="h-4 w-4 text-[#f31976]" />
                </div>
                <div>
                  <p className="text-xs font-black text-slate-950">Email de confirmation envoyé</p>
                  <p className="mt-0.5 text-xs font-semibold leading-5 text-slate-500">
                    {paymentConfirmation.client?.email ?? paymentConfirmation.reservation?.client?.email}
                  </p>
                </div>
              </div>
            )}

            <button
              type="button"
              onClick={() => setPaymentConfirmation(null)}
              className="mt-6 w-full rounded-2xl bg-[#f31976] py-3.5 text-sm font-black text-white transition hover:bg-[#d6165e]"
            >
              Retour à l'accueil
            </button>
          </div>
        </div>
      )}

      <header className="fixed top-0 left-0 right-0 z-30 border-b border-[#f7d6e5] bg-white/95 backdrop-blur">
        <div className="mx-auto w-full max-w-[1440px] px-3 py-2 sm:px-5 lg:px-8">
          <div className="flex items-center gap-2 lg:grid lg:grid-cols-[auto_1fr_auto] lg:gap-3">
            <button type="button" onClick={() => navigate('/')} className="flex shrink-0 items-center gap-3 text-left">
              <img src="/logo-bichette.jpg" alt="Bichette Thomas" className="h-11 w-11 shrink-0 rounded-2xl object-cover object-center sm:h-12 sm:w-12" />
              <div className="hidden min-w-0 sm:block">
                <p className="font-display text-xl leading-5 text-slate-950 sm:text-2xl">Bichette <span className="text-[#f31976]">Thomas</span></p>
                <p className="mt-1 flex items-center gap-1 text-[11px] font-bold text-slate-500">
                  <MapPin className="h-3.5 w-3.5 text-[#f31976]" />
                  Dakar, Senegal
                </p>
              </div>
            </button>

            <label className="relative flex h-11 min-w-0 flex-1 items-center lg:mx-auto lg:w-full lg:max-w-xl">
              <Search className="pointer-events-none absolute left-3 h-4 w-4 text-slate-400 sm:left-4" />
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Rechercher"
                className="h-full w-full rounded-full border border-slate-200 bg-white pl-9 pr-3 text-base font-bold outline-none transition focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10 sm:pl-10 sm:pr-4 sm:text-sm"
              />
            </label>

            <div className="flex shrink-0 items-center justify-end gap-2">
              <nav className="hidden min-w-0 flex-1 gap-1 overflow-x-auto sm:flex lg:flex-none lg:gap-2">
                {[
                  { label: 'Accueil', icon: Home, onClick: () => navigate('/') },
                  { label: 'A propos', icon: Users, onClick: () => navigate('/#apropos') },
                  { label: 'Contact', icon: MessageCircle, onClick: () => navigate('/#contact') },
                ].map((item) => {
                  const Icon = item.icon
                  return (
                    <button
                      key={item.label}
                      type="button"
                      onClick={item.onClick}
                      className="inline-flex h-10 shrink-0 items-center gap-2 px-2.5 text-[10px] font-black uppercase tracking-[0.13em] text-slate-600 transition hover:bg-[#fff0f6] hover:text-[#f31976] sm:px-3 sm:text-xs"
                    >
                      <Icon className="h-4 w-4" />
                      {item.label}
                    </button>
                  )
                })}
              </nav>
              <button
                type="button"
                className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#fff0f6] text-[#f31976] shadow-sm sm:hidden"
                aria-label="Profil client"
              >
                <User className="h-5 w-5" />
              </button>
              <button type="button" className="hidden h-11 w-11 shrink-0 place-items-center rounded-full border border-slate-200 bg-white text-slate-800 shadow-sm sm:grid" aria-label="Notifications">
                <Bell className="h-5 w-5" />
              </button>
              <button type="button" className="hidden h-11 w-11 shrink-0 place-items-center rounded-full bg-[#fff0f6] text-[#f31976] sm:grid" aria-label="Profil client">
                <User className="h-5 w-5" />
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* Spacer compensant la hauteur du header fixed (~60px) */}
      <div className="h-[60px]" />

      <div className="mx-auto w-full max-w-[1440px] px-3 pb-12 pt-3 sm:px-5 lg:px-8">
        {submitMessage ? (
          <div className="mt-3 bg-[#fff0f6] px-5 py-4 text-sm font-bold text-[#b01258]">
            {submitMessage}
          </div>
        ) : null}

        <main>
          <section className="bg-white py-8 sm:py-10">
            <div className="mx-auto max-w-4xl text-center">
              <p className="text-[10px] font-black uppercase tracking-[0.34em] text-slate-400">
                <button type="button" onClick={() => navigate('/')} className="hover:text-[#f31976]">
                  Accueil
                </button>
                <span className="mx-2 text-slate-300">/</span> <span className="text-slate-950">Categorie</span>
              </p>
              <h1 className="mx-auto mt-5 max-w-5xl text-3xl font-light uppercase leading-tight tracking-[0.16em] text-slate-950 sm:text-5xl lg:text-6xl">
                {activeCategory?.nom ?? 'Toutes les coiffures'}
              </h1>
              <p className="mx-auto mt-5 max-w-2xl text-sm font-semibold leading-6 text-slate-500 sm:leading-7">
                {activeCategory?.description ?? 'Explorez les styles disponibles et ouvrez les détails pour comparer les photos, les durées et les prix.'}
              </p>
            </div>
          </section>

          <section className="border-y border-[#f0e6eb] bg-[#fdfbfd]">
            <div className="flex min-h-16 items-center justify-between gap-4 px-2 text-xs font-black uppercase tracking-[0.18em] text-slate-500">
              <div className="flex items-center gap-5">
                <span className="text-slate-950">Categories</span>
                <span className="hidden h-5 w-px bg-slate-200 sm:block" />
                <span>{coiffures.length} coiffure(s)</span>
              </div>
              <span className="hidden text-slate-950 sm:inline">Trier par nouveautes</span>
            </div>
          </section>

          <section className="border-b border-[#f7d6e5] bg-white py-5">
            <div className="flex gap-8 overflow-x-auto px-1 pb-3 sm:gap-10 lg:justify-center">
              <button
                type="button"
                onClick={() => navigate('/categories')}
                className="group flex min-w-[82px] flex-col items-center gap-3"
              >
                <span className={`grid h-20 w-20 place-items-center rounded-full border-2 text-xs font-black uppercase tracking-[0.14em] transition sm:h-24 sm:w-24 ${!parsedCategoryId ? 'border-[#f31976] bg-[#f31976] text-white' : 'border-transparent bg-[#fff0f6] text-[#f31976] group-hover:border-[#f31976]'}`}>
                  Tout
                </span>
                <span className="w-24 truncate text-center text-[11px] font-black uppercase tracking-[0.18em] text-slate-600">Tous styles</span>
              </button>
              {categories.map((category) => (
                <button
                  key={category.id}
                  type="button"
                  onClick={() => navigate(`/categories/${category.id}`)}
                  className="group flex min-w-[82px] flex-col items-center gap-3"
                >
                  <span className={`h-20 w-20 overflow-hidden rounded-full border-2 bg-[#fff0f6] transition sm:h-24 sm:w-24 ${category.id === parsedCategoryId ? 'border-[#f31976]' : 'border-transparent group-hover:border-[#f31976]'}`}>
                    {category.image ? (
                      <img src={category.image} alt="" className="h-full w-full object-cover transition duration-500 group-hover:scale-110" loading="lazy" />
                    ) : (
                      <span className="grid h-full w-full place-items-center text-[#f31976]">
                        <Scissors className="h-7 w-7" />
                      </span>
                    )}
                  </span>
                  <span className="w-24 truncate text-center text-[11px] font-black uppercase tracking-[0.18em] text-slate-600 group-hover:text-[#f31976]">
                    {category.nom}
                  </span>
                </button>
              ))}
            </div>
          </section>

          <section className="py-8">
            {error ? <div className="bg-rose-50 px-5 py-4 text-sm font-bold text-rose-700">{error}</div> : null}
            {loading ? (
              <div className="grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                {Array.from({ length: 12 }, (_, index) => <div key={index} className="h-52 animate-pulse bg-white" />)}
              </div>
            ) : coiffures.length > 0 ? (
              <div className="grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                {coiffures.map((coiffure, index) => (
                  <Reveal key={coiffure.id} delay={Math.min((index % 12) * 70, 420)}>
                    <CoiffureCard
                      coiffure={coiffure}
                      isFavorite={emptyFavorites.includes(coiffure.id)}
                      devise={devise}
                      onToggleFavorite={() => {}}
                      onOpenDetails={openDetails}
                    />
                  </Reveal>
                ))}
              </div>
            ) : (
              <div className="bg-white px-5 py-16 text-center text-sm font-bold text-slate-500">
                Aucune coiffure active dans cette categorie pour le moment.
              </div>
            )}
          </section>
        </main>

        <footer className="mt-6 border-t border-[#f7d6e5] bg-white px-5 py-6 text-xs font-bold text-slate-500">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <span>Bichette Thomas - Dakar, Senegal</span>
            {catalogue?.settings.telephone_whatsapp ? (
              <a href={`https://wa.me/${catalogue.settings.telephone_whatsapp.replace(/\D/g, '')}`} className="inline-flex items-center gap-2 text-[#f31976]">
                <Phone className="h-4 w-4" />
                WhatsApp {catalogue.settings.telephone_whatsapp}
              </a>
            ) : null}
          </div>
        </footer>
      </div>

      {selectedCoiffure ? (
        <ReservationModal
          coiffure={selectedCoiffure}
          settings={settings}
          devise={devise}
          promotions={catalogue?.promotions ?? []}
          onClose={() => setSelectedCoiffure(null)}
        />
      ) : null}

      {/* Bouton WhatsApp flottant : discret, halo qui pulse par intermittence. */}
      {settings?.telephone_whatsapp ? (
        <a
          href={`https://wa.me/${settings.telephone_whatsapp.replace(/\D/g, '')}`}
          target="_blank"
          rel="noreferrer"
          aria-label="Nous contacter sur WhatsApp"
          className="bt-whatsapp-pulse fixed bottom-5 right-4 z-40 grid h-14 w-14 place-items-center rounded-full bg-[#25D366] text-white transition hover:scale-105 active:scale-95 sm:bottom-6 sm:right-6"
        >
          <MessageCircle className="h-7 w-7" />
        </a>
      ) : null}
    </div>
  )
}

export default ClientCategoryPage
