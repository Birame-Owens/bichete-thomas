import axios from 'axios'
import {
  Bell,
  Calendar,
  CalendarCheck,
  Check,
  ChevronRight,
  Clock,
  CreditCard,
  Gift,
  Home,
  Image as ImageIcon,
  Camera,
  Loader2,
  LogOut,
  MapPin,
  MessageCircle,
  Music2,
  Phone,
  Scissors,
  Search,
  ShieldCheck,
  Sparkles,
  User,
  Users,
  X,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { type FormEvent, useCallback, useEffect, useMemo, useState } from 'react'
import PhoneInput from 'react-phone-number-input'
import 'react-phone-number-input/style.css'
import heroImage from '../../assets/hero.jpg'
import {
  confirmPaytechReturn,
  confirmStripeCheckout,
  createClientReservation,
  getClientAvailability,
  getClientCatalogue,
  getClientCoiffureDetails,
  getClientSession,
  logoutClientSession,
  verifyMagicLink,
} from './client.api'
import {
  closedDaysLabel,
  coiffureImage,
  depositAmount,
  discountAmount,
  formatCurrency,
  formatDuration,
  formatShortDate,
  isClosedDate,
  promoText,
  todayInput,
} from './client.helpers'
import { CoiffureCard } from './components/CoiffureCard'
import { RatingStars } from './components/RatingStars'
import { usePhoneLookup } from './hooks/usePhoneLookup'
import type {
  ClientAvailability,
  ClientCatalogue,
  ClientCategory,
  ClientCoiffure,
  ClientCoiffureOption,
  ClientPaymentMethod,
  ClientPromotion,
  ClientReservation,
  ClientReservationPayload,
  ClientSession,
} from './client.types'

type BookingForm = {
  prenom: string
  nom: string
  telephone: string
  email: string
  date_reservation: string
  heure_debut: string
  varianteId: string
  optionIds: number[]
  code_promo: string
  notes: string
  paymentMethod: ClientPaymentMethod
}

type SubmitState = {
  type: 'success' | 'error'
  message: string
} | null

type ClientNavItem = {
  id: string
  label: string
  icon: LucideIcon
}

const clientNavItems: ClientNavItem[] = [
  { id: 'accueil', label: 'Accueil', icon: Home },
  { id: 'catalogue', label: 'Coiffures', icon: Scissors },
  { id: 'promos', label: 'Promos', icon: Gift },
  { id: 'reservations', label: 'Reserver', icon: Calendar },
  { id: 'contact', label: 'Contact', icon: MessageCircle },
]

const benefits: Array<{ label: string; detail: string; icon: LucideIcon }> = [
  { label: 'Coiffeuses', detail: 'Expertes', icon: Users },
  { label: 'Produits', detail: 'Selection premium', icon: Sparkles },
  { label: 'Paiement', detail: 'Acompte clair', icon: CreditCard },
  { label: 'Satisfaction', detail: 'Suivi attentif', icon: ShieldCheck },
]

const paymentMethods: Array<{
  value: ClientPaymentMethod
  label: string
  detail: string
  icon: LucideIcon
  logo?: string
}> = [
  { value: 'wave', label: 'Wave', detail: 'Paiement securise via PayTech', icon: Phone, logo: '/wave logo.webp' },
  { value: 'orange_money', label: 'Orange Money', detail: 'Paiement securise via PayTech', icon: Phone, logo: '/om logo.webp' },
  { value: 'carte_bancaire', label: 'Carte bancaire', detail: 'Paiement securise Stripe', icon: CreditCard },
]

const instagramUrl = 'https://www.instagram.com/bichette_thomas/'
const tiktokUrl = 'https://www.tiktok.com/@bichette_thomas'
const mapsUrl = 'https://www.google.com/maps/?cid=9724705947575440818'
const mapsEmbedUrl = 'https://www.google.com/maps?cid=9724705947575440818&output=embed'

const emptyCategories: ClientCategory[] = []
const emptyCoiffures: ClientCoiffure[] = []
const emptyPromotions: ClientPromotion[] = []

// Les helpers de formatage / calcul / dates sont externalises dans
// ./client.helpers.ts pour permettre le memo correct des sous-composants
// (CoiffureCard, etc.). Les composants RatingStars et CoiffureCard sont
// dans ./components/ et memoizes (I11).

function createBookingForm(coiffure?: ClientCoiffure): BookingForm {
  return {
    prenom: '',
    nom: '',
    telephone: '',
    email: '',
    date_reservation: todayInput(),
    heure_debut: '',
    varianteId: coiffure?.variantes[0]?.id ? String(coiffure.variantes[0].id) : '',
    optionIds: [],
    code_promo: '',
    notes: '',
    paymentMethod: 'wave',
  }
}

function extractApiError(error: unknown) {
  if (axios.isAxiosError(error)) {
    const payload = error.response?.data as {
      message?: string
      errors?: Record<string, string[]>
    } | undefined

    if (payload?.errors) {
      const firstError = Object.values(payload.errors)[0]?.[0]
      if (firstError) {
        return firstError
      }
    }

    if (payload?.message) {
      return payload.message
    }
  }

  return 'Impossible de finaliser la reservation pour le moment.'
}

function scrollToSection(sectionId: string) {
  document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function ClientHomePage() {
  const [catalogue, setCatalogue] = useState<ClientCatalogue | null>(null)
  const [loading, setLoading] = useState(true)
  const [catalogueError, setCatalogueError] = useState<string | null>(null)
  const [activeCategoryId, setActiveCategoryId] = useState<number | null>(null)
  const [search, setSearch] = useState('')
  const [favoriteIds, setFavoriteIds] = useState<number[]>([])
  const [selectedCoiffure, setSelectedCoiffure] = useState<ClientCoiffure | null>(null)
  const [modalLoading, setModalLoading] = useState(false)
  const [bookingForm, setBookingForm] = useState<BookingForm>(() => createBookingForm())
  // Phase 5 etape 1 : lookup tel international + prefill auto.
  // Le hook debounce 300ms et n appelle l API que sur E.164 valide (libphonenumber local).
  const phoneLookup = usePhoneLookup(bookingForm.telephone)
  const [availability, setAvailability] = useState<ClientAvailability | null>(null)
  const [availabilityLoading, setAvailabilityLoading] = useState(false)
  const [availabilityError, setAvailabilityError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [submitState, setSubmitState] = useState<SubmitState>(null)
  const [pageNotice, setPageNotice] = useState<SubmitState>(null)
  const [submittedReservation, setSubmittedReservation] = useState<ClientReservation | null>(null)
  const [clientSession, setClientSession] = useState<ClientSession | null>(null)

  // Prefill non-destructif nom/prenom quand le backend retrouve un client par tel
  // (Phase 5 etape 1). On NE PAS ECRASER ce que la cliente a deja tape : si elle
  // a deja saisi son prenom avant que le tel devienne valide, on le respecte.
  // Si elle veut le changer (faute de frappe en base, nom marital, etc.) elle
  // peut le retaper a la main - le backend stockera le nom historique vu que
  // ClientResolver matche sur tel uniquement.
  useEffect(() => {
    if (phoneLookup.state !== 'found' || phoneLookup.data === null) {
      return
    }
    setBookingForm((current) => ({
      ...current,
      prenom: current.prenom.trim() === '' ? phoneLookup.data?.prenom ?? '' : current.prenom,
      nom: current.nom.trim() === '' ? phoneLookup.data?.nom ?? '' : current.nom,
    }))
  }, [phoneLookup.state, phoneLookup.data])

  // Phase 5 etape 2 : verifie silencieusement si un cookie de session valide
  // existe deja. 401 = pas de session = silence. Permet le prefill auto meme
  // quand la cliente revient directement sur la page sans passer par le lien.
  useEffect(() => {
    let ignore = false
    getClientSession()
      .then((data) => {
        if (!ignore) setClientSession(data)
      })
      .catch(() => {})
    return () => {
      ignore = true
    }
  }, [])

  // Prefill non-destructif depuis la session active (Phase 5 etape 2).
  // Complemente le prefill du phoneLookup (etape 1) en ajoutant le telephone.
  // S execute quand la session change OU quand le modal s ouvre (selectedCoiffure).
  useEffect(() => {
    if (!clientSession || !selectedCoiffure) {
      return
    }
    setBookingForm((current) => ({
      ...current,
      telephone: current.telephone.trim() === '' ? clientSession.telephone : current.telephone,
      prenom: current.prenom.trim() === '' ? clientSession.prenom : current.prenom,
      nom: current.nom.trim() === '' ? clientSession.nom : current.nom,
    }))
  }, [clientSession, selectedCoiffure])

  useEffect(() => {
    let ignore = false

    getClientCatalogue()
      .then((data) => {
        if (!ignore) {
          setCatalogue(data)
          setCatalogueError(null)
        }
      })
      .catch(() => {
        if (!ignore) {
          setCatalogueError('Le catalogue client est indisponible pour le moment.')
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

    // Magic link WhatsApp (Phase 5 etape 2) : le lien contient ?magic_token=...
    // On consomme le token immediatement et on nettoie l URL pour qu un reload
    // ne le rejoue pas (le token est single-use cote backend de toute facon).
    const magicToken = params.get('magic_token')
    if (magicToken) {
      window.history.replaceState({}, '', window.location.pathname)
      verifyMagicLink(magicToken)
        .then((response) => {
          setClientSession(response.data)
          setPageNotice({
            type: 'success',
            message: `Connexion reussie. Bonjour ${response.data.prenom} !`,
          })
        })
        .catch(() => {
          setPageNotice({
            type: 'error',
            message: 'Ce lien de connexion est invalide ou a deja ete utilise.',
          })
        })
      return
    }

    const sessionId = params.get('stripe_session_id')
    const paymentStatus = params.get('paiement')

    if (paymentStatus === 'stripe_cancel') {
      window.setTimeout(() => {
        setPageNotice({ type: 'error', message: 'Paiement carte annule. La reservation reste en attente.' })
      }, 0)
      return
    }

    if (paymentStatus === 'paytech_cancel') {
      window.setTimeout(() => {
        setPageNotice({ type: 'error', message: 'Paiement PayTech annule. Le creneau ne sera pas confirme.' })
      }, 0)
      return
    }

    if (paymentStatus === 'paytech_success') {
      const paymentId = params.get('paiement_id')
      const signature = params.get('signature')

      if (!paymentId || !signature) {
        window.setTimeout(() => {
          setPageNotice({
            type: 'success',
            message: 'Paiement PayTech recu. La confirmation automatique sera appliquee par notification IPN.',
          })
          window.history.replaceState({}, '', window.location.pathname)
        }, 0)
        return
      }

      confirmPaytechReturn(paymentId, signature)
        .then((response) => {
          setPageNotice({
            type: 'success',
            message: response.message ?? 'Paiement PayTech valide. Votre reservation est securisee.',
          })
          window.history.replaceState({}, '', window.location.pathname)
        })
        .catch(() => {
          setPageNotice({
            type: 'success',
            message: 'Paiement PayTech recu. La confirmation automatique sera appliquee par notification IPN.',
          })
          window.history.replaceState({}, '', window.location.pathname)
        })
      return
    }

    if (!sessionId) {
      return
    }

    confirmStripeCheckout(sessionId)
      .then((response) => {
        setPageNotice({
          type: 'success',
          message: response.message ?? 'Paiement carte valide. Votre reservation est securisee.',
        })
        window.history.replaceState({}, '', window.location.pathname)
      })
      .catch(() => {
        setPageNotice({
          type: 'error',
          message: 'Stripe n a pas encore confirme ce paiement. Il restera visible en attente dans l admin.',
        })
      })
  }, [])

  const settings = catalogue?.settings

  useEffect(() => {
    if (!selectedCoiffure || bookingForm.date_reservation === '') {
      return
    }

    if (isClosedDate(bookingForm.date_reservation, settings)) {
      setAvailability(null)
      setAvailabilityError('Le salon est ferme ce jour-la.')
      setAvailabilityLoading(false)
      setBookingForm((current) => ({
        ...current,
        heure_debut: '',
      }))
      return
    }

    let ignore = false

    getClientAvailability(bookingForm.date_reservation, 60)
      .then((data) => {
        if (ignore) {
          return
        }

        setAvailability(data)
        setBookingForm((current) => {
          const currentSlot = data.creneaux.find((slot) => slot.heure === current.heure_debut)
          const firstAvailableSlot = data.creneaux.find((slot) => slot.disponible)

          if (current.heure_debut !== '' && currentSlot?.disponible) {
            return current
          }

          return {
            ...current,
            heure_debut: firstAvailableSlot?.heure ?? '',
          }
        })
      })
      .catch(() => {
        if (!ignore) {
          setAvailabilityError('Les horaires ne sont pas disponibles pour cette date.')
          setAvailability(null)
        }
      })
      .finally(() => {
        if (!ignore) {
          setAvailabilityLoading(false)
        }
      })

    return () => {
      ignore = true
    }
  }, [selectedCoiffure, bookingForm.date_reservation, settings])

  const categories = catalogue?.categories ?? emptyCategories
  const coiffures = catalogue?.coiffures ?? emptyCoiffures
  const promotions = catalogue?.promotions ?? emptyPromotions
  const devise = settings?.devise ?? 'FCFA'

  const filteredCoiffures = useMemo(() => {
    const query = search.trim().toLowerCase()

    return coiffures.filter((coiffure) => {
      const matchesCategory = activeCategoryId === null || coiffure.categorie?.id === activeCategoryId
      const matchesSearch =
        query === ''
        || coiffure.nom.toLowerCase().includes(query)
        || (coiffure.description ?? '').toLowerCase().includes(query)
        || (coiffure.categorie?.nom ?? '').toLowerCase().includes(query)

      return matchesCategory && matchesSearch
    })
  }, [activeCategoryId, coiffures, search])

  const featuredCoiffures = filteredCoiffures.slice(0, 8)
  const selectedVariant = selectedCoiffure?.variantes.find((variant) => String(variant.id) === bookingForm.varianteId)
  const selectedOptions = selectedCoiffure?.options.filter((option) => bookingForm.optionIds.includes(option.id)) ?? []
  const selectedPromo =
    bookingForm.code_promo.trim() === ''
      ? null
      : promotions.find((promo) => promo.code.toLowerCase() === bookingForm.code_promo.trim().toLowerCase()) ?? null
  const subtotal =
    (selectedVariant ? Number(selectedVariant.prix) : 0)
    + selectedOptions.reduce((sum, option) => sum + Number(option.prix), 0)
  const discount = discountAmount(selectedPromo, subtotal)
  const total = Math.max(subtotal - discount, 0)
  const deposit = depositAmount(total, settings)
  const selectedPaymentMethod = paymentMethods.find((method) => method.value === bookingForm.paymentMethod)

  function updateBookingField<K extends keyof BookingForm>(key: K, value: BookingForm[K]) {
    setBookingForm((current) => ({
      ...current,
      [key]: value,
    }))
  }

  // useCallback pour stabiliser les references entre re-renders et permettre
  // au memo de CoiffureCard de fonctionner (sinon nouveau callback a chaque
  // render -> memo casse -> 8 cartes re-rendent a chaque frappe). Pareil
  // pour openDetails plus bas.
  const toggleFavorite = useCallback((id: number) => {
    setFavoriteIds((current) =>
      current.includes(id) ? current.filter((favoriteId) => favoriteId !== id) : [...current, id],
    )
  }, [])

  function toggleOption(option: ClientCoiffureOption) {
    setBookingForm((current) => {
      const selected = current.optionIds.includes(option.id)

      return {
        ...current,
        optionIds: selected
          ? current.optionIds.filter((optionId) => optionId !== option.id)
          : [...current.optionIds, option.id],
      }
    })
  }

  function closeDetails() {
    setSelectedCoiffure(null)
    setSubmitState(null)
    setSubmittedReservation(null)
    setAvailability(null)
    setAvailabilityLoading(false)
  }

  async function handleLogout() {
    try {
      await logoutClientSession()
    } finally {
      setClientSession(null)
    }
  }

  // useCallback : stabilise la reference pour que CoiffureCard memo bite.
  // Toutes les valeurs capturees sont des setters (stables) ou des helpers
  // au niveau module (stables) donc deps array vide suffit.
  const openDetails = useCallback(async (coiffure: ClientCoiffure) => {
    setSelectedCoiffure(coiffure)
    setBookingForm(createBookingForm(coiffure))
    setSubmitState(null)
    setSubmittedReservation(null)
    setAvailability(null)
    setAvailabilityLoading(true)
    setAvailabilityError(null)
    setModalLoading(true)

    try {
      const details = await getClientCoiffureDetails(coiffure.id)
      setSelectedCoiffure(details)
      setBookingForm((current) => ({
        ...current,
        varianteId: details.variantes[0]?.id ? String(details.variantes[0].id) : current.varianteId,
      }))
    } catch {
      setSubmitState({ type: 'error', message: 'Impossible de charger tous les details de cette coiffure.' })
    } finally {
      setModalLoading(false)
    }
  }, [])

  async function handleReservationSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (!selectedCoiffure || !selectedVariant) {
      setSubmitState({ type: 'error', message: 'Choisissez une coiffure et une variante.' })
      return
    }

    if (isClosedDate(bookingForm.date_reservation, settings)) {
      setSubmitState({ type: 'error', message: 'Le salon est ferme ce jour-la.' })
      return
    }

    if (bookingForm.heure_debut === '') {
      setSubmitState({ type: 'error', message: 'Choisissez un horaire disponible.' })
      return
    }

    const isCardPayment = bookingForm.paymentMethod === 'carte_bancaire'
    const payload: ClientReservationPayload = {
      client: {
        nom: bookingForm.nom.trim(),
        prenom: bookingForm.prenom.trim(),
        telephone: bookingForm.telephone.trim(),
        email: bookingForm.email.trim() === '' ? null : bookingForm.email.trim(),
      },
      coiffure_id: selectedCoiffure.id,
      variante_coiffure_id: selectedVariant.id,
      option_ids: bookingForm.optionIds,
      date_reservation: bookingForm.date_reservation,
      heure_debut: bookingForm.heure_debut,
      code_promo: bookingForm.code_promo.trim() === '' ? null : bookingForm.code_promo.trim(),
      notes: bookingForm.notes.trim() === '' ? null : bookingForm.notes.trim(),
      mode_paiement: bookingForm.paymentMethod,
      reference_paiement: null,
      success_url: isCardPayment
        ? `${window.location.origin}${window.location.pathname}?paiement=stripe_success&stripe_session_id={CHECKOUT_SESSION_ID}`
        : `${window.location.origin}${window.location.pathname}?paiement=paytech_success`,
      cancel_url: `${window.location.origin}${window.location.pathname}?paiement=${isCardPayment ? 'stripe_cancel' : 'paytech_cancel'}`,
    }

    setSubmitting(true)
    setSubmitState(null)

    try {
      const response = await createClientReservation(payload)
      if (response.requires_redirect && response.checkout_url) {
        window.location.href = response.checkout_url
        return
      }

      setSubmittedReservation(response.data)
      setSubmitState({
        type: 'success',
        message: response.message ?? 'Paiement enregistre. Le salon validera la transaction.',
      })
    } catch (error) {
      setSubmitState({ type: 'error', message: extractApiError(error) })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="min-h-screen bg-[#fbf8fa] text-slate-950">
      <div className="mx-auto w-full max-w-[1320px] px-3 pb-12 pt-3 sm:px-5 lg:px-6">
        <header
          id="accueil"
          className="z-30 rounded-[28px] border border-slate-100 bg-white/95 p-3 shadow-sm backdrop-blur lg:sticky lg:top-3"
        >
          <div className="flex flex-wrap items-center gap-3">
            <div className="flex min-w-[220px] flex-1 items-center gap-3">
              <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[#f31976] text-sm font-black text-white">
                BT
              </div>
              <div className="min-w-0">
                <p className="font-display text-2xl leading-6 text-slate-950">
                  Bichette <span className="text-[#f31976]">Thomas</span>
                </p>
                <p className="mt-1 flex items-center gap-1 text-xs font-bold text-slate-500">
                  <MapPin className="h-3.5 w-3.5 text-[#f31976]" />
                  Dakar, Senegal
                </p>
              </div>
            </div>

            <nav className="order-3 flex w-full gap-2 overflow-x-auto pt-1 lg:order-none lg:w-auto lg:flex-none lg:pt-0">
              {clientNavItems.map((item) => {
                const Icon = item.icon

                return (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => scrollToSection(item.id)}
                    className="inline-flex h-10 shrink-0 items-center gap-2 rounded-full px-3 text-sm font-black text-slate-600 transition hover:bg-[#fff0f6] hover:text-[#f31976]"
                  >
                    <Icon className="h-4 w-4" />
                    {item.label}
                  </button>
                )
              })}
            </nav>

            <div className="flex w-full items-center gap-2 sm:w-auto lg:ml-auto">
              <label className="relative flex h-11 min-w-0 flex-1 items-center sm:w-64 sm:flex-none">
                <Search className="pointer-events-none absolute left-4 h-4 w-4 text-slate-400" />
                <input
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Rechercher"
                  className="h-full w-full rounded-full border border-slate-200 bg-white pl-10 pr-4 text-sm font-bold outline-none transition focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                />
              </label>
              <button
                type="button"
                className="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-slate-200 bg-white text-slate-800 shadow-sm"
                aria-label="Notifications"
              >
                <Bell className="h-5 w-5" />
              </button>
              {clientSession ? (
                <button
                  type="button"
                  onClick={handleLogout}
                  title="Se deconnecter"
                  className="flex h-11 shrink-0 items-center gap-2 rounded-full bg-[#fff0f6] px-3 text-sm font-black text-[#f31976]"
                >
                  <User className="h-4 w-4" />
                  <span className="hidden sm:inline">{clientSession.prenom}</span>
                  <LogOut className="h-4 w-4" />
                </button>
              ) : (
                <button
                  type="button"
                  className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#fff0f6] text-[#f31976]"
                  aria-label="Profil client"
                >
                  <User className="h-5 w-5" />
                </button>
              )}
            </div>
          </div>
        </header>

        <main className="mt-5">
          {pageNotice ? (
            <div
              className={`mb-5 rounded-3xl px-5 py-4 text-sm font-bold ${
                pageNotice.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
              }`}
            >
              {pageNotice.message}
            </div>
          ) : null}

          <section className="relative mt-3 overflow-hidden rounded-[28px] bg-[#f31976] text-white shadow-lg">
            <div className="absolute inset-0">
              <img src={heroImage} alt="" className="h-full w-full object-cover object-[68%_center] opacity-90 sm:object-center" />
              <div className="absolute inset-0 bg-gradient-to-br from-[#f31976]/95 via-[#f31976]/88 to-[#f31976]/52 sm:bg-gradient-to-r sm:from-[#f31976] sm:via-[#f31976]/72 sm:to-transparent" />
            </div>
            <div className="relative grid min-h-[430px] items-center gap-6 p-6 sm:min-h-72 sm:p-9 lg:grid-cols-[1.05fr_0.95fr]">
              <div className="max-w-xl">
                <p className="text-sm font-black uppercase text-white/75">Reservation en ligne</p>
                <h2 className="mt-3 text-4xl font-black leading-tight sm:text-5xl">Revelez votre beaute</h2>
                <p className="mt-4 max-w-sm text-base font-semibold leading-7 text-white/85">
                  Choisissez votre coiffure, trouvez un horaire libre et envoyez votre demande en quelques clics.
                </p>
                <button
                  type="button"
                  onClick={() => scrollToSection('catalogue')}
                  className="mt-6 inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-black text-[#d80f63] shadow-sm"
                >
                  Reserver maintenant
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          </section>

          {catalogueError ? (
            <div className="mt-5 rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-700">
              {catalogueError}
            </div>
          ) : null}

          <section id="categories" className="mt-7">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-xl font-black text-slate-950">Categories</h2>
              <div className="text-right text-sm font-bold text-slate-500">
                <p>{settings?.heure_ouverture ?? '09:00'} - {settings?.heure_fermeture ?? '19:00'}</p>
                <p className="text-xs font-semibold text-slate-400">
                  {closedDaysLabel(settings?.jours_fermeture ?? [])}
                </p>
              </div>
            </div>

            <div className="mt-4 flex gap-3 overflow-x-auto pb-2">
              {categories.map((category) => (
                <button
                  key={category.id}
                  type="button"
                  onClick={() => setActiveCategoryId(category.id)}
                  className={`grid min-h-20 min-w-24 place-items-center rounded-3xl border px-4 text-center text-sm font-black transition ${
                    activeCategoryId === category.id
                      ? 'border-[#f31976] bg-[#f31976] text-white'
                      : 'border-slate-100 bg-white text-slate-800'
                  }`}
                >
                  {category.image ? (
                    <img src={category.image} alt="" className="mb-2 h-9 w-9 rounded-2xl object-cover" />
                  ) : null}
                  {category.nom}
                </button>
              ))}
            </div>
          </section>

          <section id="catalogue" className="mt-7">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h2 className="text-2xl font-black text-slate-950">Nos coiffures populaires</h2>
                <p className="mt-1 text-sm font-semibold text-slate-500">{featuredCoiffures.length} coiffure(s) disponible(s)</p>
              </div>
              <button
                type="button"
                onClick={() => {
                  setActiveCategoryId(null)
                  setSearch('')
                }}
                className="text-sm font-black text-[#f31976]"
              >
                Voir tout
              </button>
            </div>

            {loading ? (
              <div className="mt-5 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                {Array.from({ length: 8 }, (_, index) => (
                  <div key={index} className="h-72 animate-pulse rounded-3xl bg-white" />
                ))}
              </div>
            ) : (
              <div className="mt-5 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                {featuredCoiffures.map((coiffure) => (
                  <CoiffureCard
                    key={coiffure.id}
                    coiffure={coiffure}
                    isFavorite={favoriteIds.includes(coiffure.id)}
                    devise={devise}
                    onToggleFavorite={toggleFavorite}
                    onOpenDetails={openDetails}
                  />
                ))}
              </div>
            )}
          </section>

          <section id="promos" className="mt-7">
            {promotions.length > 0 ? (
              <div className="grid gap-4 lg:grid-cols-2">
                {promotions.map((promo) => (
                  <article key={promo.id} className="flex items-center justify-between gap-4 rounded-3xl bg-[#fff0f6] p-5 shadow-sm">
                    <div>
                      <p className="text-base font-black text-[#d80f63]">{promoText(promo)} sur votre reservation</p>
                      <p className="mt-2 text-sm font-semibold text-slate-700">
                        Code : <span className="font-black text-slate-950">{promo.code}</span>
                      </p>
                    </div>
                    <div className="grid h-16 w-16 shrink-0 place-items-center rounded-3xl bg-white text-[#f31976]">
                      <Gift className="h-9 w-9" />
                    </div>
                  </article>
                ))}
              </div>
            ) : (
              <div className="rounded-3xl bg-white p-5 text-sm font-bold text-slate-500 shadow-sm">
                Les promotions actives apparaitront ici.
              </div>
            )}
          </section>

          <section id="reservations" className="mt-8 grid gap-5 xl:grid-cols-[1fr_360px]">
            <div>
              <h2 className="text-2xl font-black text-slate-950">Pourquoi nous choisir ?</h2>
              <div className="mt-5 grid grid-cols-2 gap-4 md:grid-cols-4">
                {benefits.map((benefit) => {
                  const Icon = benefit.icon

                  return (
                    <article key={benefit.label} className="rounded-3xl bg-white p-5 text-center shadow-sm">
                      <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#fff0f6] text-[#f31976]">
                        <Icon className="h-6 w-6" />
                      </div>
                      <p className="mt-3 text-sm font-black text-slate-950">{benefit.label}</p>
                      <p className="mt-1 text-xs font-bold text-slate-500">{benefit.detail}</p>
                    </article>
                  )
                })}
              </div>
            </div>

            <aside id="contact" className="rounded-3xl bg-white p-5 shadow-sm">
              <p className="text-lg font-black text-slate-950">Reservation rapide</p>
              <p className="mt-2 text-sm font-semibold text-slate-500">
                Choisissez une coiffure dans le catalogue et envoyez votre demande au salon.
              </p>
              <button
                type="button"
                onClick={() => scrollToSection('catalogue')}
                className="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#f31976] px-4 py-3 text-sm font-black text-white"
              >
                <CalendarCheck className="h-5 w-5" />
                Voir les coiffures
              </button>
              {settings?.telephone_whatsapp ? (
                <a
                  href={`https://wa.me/${settings.telephone_whatsapp.replace(/\D/g, '')}`}
                  className="mt-3 flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-950"
                >
                  <Phone className="h-5 w-5 text-[#f31976]" />
                  {settings.telephone_whatsapp}
                </a>
              ) : null}
              <div className="mt-5 rounded-3xl border border-slate-100 bg-[#fff8fb] p-4">
                <p className="text-sm font-black text-slate-950">Suivez-nous</p>
                <div className="mt-3 grid gap-2">
                  <a
                    href={instagramUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="flex items-center justify-between rounded-2xl bg-white px-3 py-2 text-xs font-black text-slate-700"
                  >
                    <span className="inline-flex items-center gap-2">
                      <Camera className="h-4 w-4 text-[#f31976]" />
                      Instagram
                    </span>
                    <span className="text-[#f31976]">@bichette_thomas</span>
                  </a>
                  <a
                    href={tiktokUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="flex items-center justify-between rounded-2xl bg-white px-3 py-2 text-xs font-black text-slate-700"
                  >
                    <span className="inline-flex items-center gap-2">
                      <Music2 className="h-4 w-4 text-[#f31976]" />
                      TikTok
                    </span>
                    <span className="text-[#f31976]">@bichette_thomas</span>
                  </a>
                </div>
              </div>
            </aside>
          </section>

          <section className="mt-8 rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-xs font-black uppercase tracking-[0.18em] text-[#f31976]">Nous trouver</p>
                <h3 className="mt-2 text-2xl font-black text-slate-950">Salon Bichette Thomas</h3>
                <p className="mt-1 text-sm font-semibold text-slate-500">
                  Consultez la position exacte du salon sur la carte.
                </p>
              </div>
              <a
                href={mapsUrl}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-2 rounded-full border border-slate-200 px-4 py-2 text-xs font-black text-slate-700"
              >
                <MapPin className="h-4 w-4 text-[#f31976]" />
                Ouvrir dans Google Maps
              </a>
            </div>
            <div className="mt-4 overflow-hidden rounded-3xl border border-slate-100">
              <iframe
                title="Carte du salon Bichette Thomas"
                src={mapsEmbedUrl}
                className="h-72 w-full"
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
              />
            </div>
          </section>
        </main>

        <footer className="mt-10 flex flex-col items-center justify-between gap-3 rounded-3xl bg-white px-6 py-4 text-xs font-bold text-slate-500 sm:flex-row">
          <span>© 2026 Bichette Thomas · Tous droits reserves.</span>
          {settings?.telephone_whatsapp ? (
            <a
              href={`https://wa.me/${settings.telephone_whatsapp.replace(/\D/g, '')}`}
              className="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 text-xs font-black text-slate-700"
            >
              <Phone className="h-3.5 w-3.5 text-[#f31976]" />
              WhatsApp {settings.telephone_whatsapp}
            </a>
          ) : null}
        </footer>
      </div>

      {selectedCoiffure ? (
        <div className="fixed inset-0 z-40 overflow-y-auto bg-slate-950/55 px-3 py-4 backdrop-blur-sm sm:px-5 sm:py-8">
          <div className="mx-auto grid max-w-6xl gap-0 overflow-hidden rounded-[28px] bg-white shadow-2xl lg:grid-cols-[0.95fr_1.05fr]">
            <section className="bg-[#fff7fb] p-4 sm:p-6">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-sm font-black text-[#f31976]">{selectedCoiffure.categorie?.nom ?? 'Coiffure'}</p>
                  <h2 className="mt-1 text-2xl font-black text-slate-950 sm:text-3xl">{selectedCoiffure.nom}</h2>
                </div>
                <button
                  type="button"
                  onClick={closeDetails}
                  className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-white text-slate-950 shadow-sm"
                  aria-label="Fermer"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              <div className="relative mt-5 overflow-hidden rounded-3xl bg-white">
                <img src={coiffureImage(selectedCoiffure)} alt={selectedCoiffure.nom} className="aspect-[4/3] w-full object-cover" />
                {modalLoading ? (
                  <div className="absolute inset-0 grid place-items-center bg-white/70">
                    <Loader2 className="h-8 w-8 animate-spin text-[#f31976]" />
                  </div>
                ) : null}
              </div>

              <div className="mt-4 grid grid-cols-3 gap-3">
                {(selectedCoiffure.images.length > 0 ? selectedCoiffure.images.slice(0, 3) : [{ id: 0, url: heroImage, alt: null, principale: true }]).map((image) => (
                  <div key={image.id} className="aspect-square overflow-hidden rounded-2xl bg-white">
                    <img src={image.url} alt={image.alt ?? ''} className="h-full w-full object-cover" />
                  </div>
                ))}
              </div>

              <p className="mt-5 text-sm font-semibold leading-7 text-slate-600">
                {selectedCoiffure.description ?? 'Une prestation soignee, adaptee a votre style et au temps disponible au salon.'}
              </p>

              <div className="mt-5 grid grid-cols-2 gap-3">
                <div className="rounded-3xl bg-white p-4">
                  <p className="text-xs font-black uppercase text-slate-400">Prix</p>
                  <p className="mt-1 text-lg font-black text-slate-950">{formatCurrency(selectedCoiffure.prix_min, devise)}</p>
                </div>
                <div className="rounded-3xl bg-white p-4">
                  <p className="text-xs font-black uppercase text-slate-400">Duree</p>
                  <p className="mt-1 text-lg font-black text-slate-950">{formatDuration(selectedCoiffure.duree_min_minutes)}</p>
                </div>
              </div>

              <div className="mt-5 grid grid-cols-2 gap-3">
                <div className="rounded-3xl bg-white p-4">
                  <p className="text-xs font-black uppercase text-slate-400">Avis</p>
                  <div className="mt-2 flex items-center gap-2">
                    <RatingStars value={selectedCoiffure.avis_resume?.moyenne ?? 0} />
                    <span className="text-sm font-black text-slate-950">
                      {selectedCoiffure.avis_resume?.total ? selectedCoiffure.avis_resume.moyenne.toFixed(1) : '0.0'}
                    </span>
                  </div>
                  <p className="mt-1 text-xs font-bold text-slate-500">{selectedCoiffure.avis_resume?.total ?? 0} commentaire(s)</p>
                </div>
                <div className="rounded-3xl bg-white p-4">
                  <p className="text-xs font-black uppercase text-slate-400">Fiabilite</p>
                  <p className="mt-1 text-lg font-black text-slate-950">
                    {selectedCoiffure.avis.some((avis) => avis.verifie) ? 'Verifie' : 'En cours'}
                  </p>
                  <p className="mt-1 text-xs font-bold text-slate-500">Commentaires clientes</p>
                </div>
              </div>

              <div className="mt-5 rounded-3xl bg-white p-4">
                <div className="flex items-center justify-between gap-3">
                  <p className="text-sm font-black text-slate-950">Commentaires clientes</p>
                  <MessageCircle className="h-4 w-4 text-[#f31976]" />
                </div>
                {selectedCoiffure.avis.length > 0 ? (
                  <div className="mt-3 space-y-3">
                    {selectedCoiffure.avis.map((avis) => (
                      <article key={avis.id} className="rounded-2xl border border-slate-100 bg-white p-3">
                        <div className="flex items-start justify-between gap-3">
                          <div className="min-w-0">
                            <p className="truncate text-sm font-black text-slate-950">{avis.nom_client}</p>
                            <div className="mt-1 flex items-center gap-2">
                              <RatingStars value={avis.note} size="xs" />
                              {avis.verifie ? <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700">Verifie</span> : null}
                            </div>
                          </div>
                          <span className="shrink-0 text-xs font-bold text-slate-400">{avis.publie_at ? formatShortDate(avis.publie_at) : ''}</span>
                        </div>
                        <p className="mt-2 text-sm font-semibold leading-6 text-slate-600">{avis.commentaire}</p>
                      </article>
                    ))}
                  </div>
                ) : (
                  <p className="mt-3 rounded-2xl bg-[#fff7fb] px-3 py-3 text-sm font-bold text-slate-500">
                    Aucun commentaire publie pour cette coiffure pour le moment.
                  </p>
                )}
              </div>

            </section>

            <form onSubmit={handleReservationSubmit} className="p-4 sm:p-6">
              <div className="flex items-center justify-between gap-4">
                <div>
                  <p className="text-sm font-black text-[#f31976]">Finaliser la demande</p>
                  <h3 className="mt-1 text-2xl font-black text-slate-950">Votre reservation</h3>
                </div>
                <div className="hidden items-center gap-2 rounded-2xl bg-[#fff0f6] px-4 py-3 text-sm font-black text-[#d80f63] sm:flex">
                  <Clock className="h-4 w-4" />
                  {settings?.limite_reservations_par_creneau ?? 3} par heure
                </div>
              </div>

              <div className="mt-6">
                <p className="text-sm font-black text-slate-950">Variante</p>
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                  {selectedCoiffure.variantes.map((variant) => (
                    <label
                      key={variant.id}
                      className={`block cursor-pointer select-none rounded-3xl border p-4 text-left transition ${
                        bookingForm.varianteId === String(variant.id)
                          ? 'border-[#f31976] bg-[#fff0f6]'
                          : 'border-slate-200 bg-white'
                      }`}
                    >
                      <input
                        type="radio"
                        name="variante"
                        value={variant.id}
                        checked={bookingForm.varianteId === String(variant.id)}
                        onChange={() => updateBookingField('varianteId', String(variant.id))}
                        className="sr-only"
                      />
                      <span className="block text-sm font-black text-slate-950">{variant.nom}</span>
                      <span className="mt-2 flex items-center justify-between gap-2 text-sm font-bold text-slate-500">
                        {formatDuration(variant.duree_minutes)}
                        <span className="text-[#f31976]">{formatCurrency(variant.prix, devise)}</span>
                      </span>
                    </label>
                  ))}
                </div>
              </div>

              {selectedCoiffure.options.length > 0 ? (
                <div className="mt-6">
                  <p className="text-sm font-black text-slate-950">Options</p>
                  <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    {selectedCoiffure.options.map((option) => {
                      const checked = bookingForm.optionIds.includes(option.id)

                      return (
                        <label
                          key={option.id}
                          className={`flex cursor-pointer select-none items-center justify-between gap-3 rounded-3xl border p-4 text-left transition ${
                            checked ? 'border-[#f31976] bg-[#fff0f6]' : 'border-slate-200 bg-white'
                          }`}
                        >
                          <input
                            type="checkbox"
                            checked={checked}
                            onChange={() => toggleOption(option)}
                            className="sr-only"
                          />
                          <span>
                            <span className="block text-sm font-black text-slate-950">{option.nom}</span>
                            <span className="mt-1 block text-xs font-bold text-slate-500">{formatCurrency(option.prix, devise)}</span>
                          </span>
                          <span className={`grid h-7 w-7 place-items-center rounded-full ${checked ? 'bg-[#f31976] text-white' : 'bg-slate-100 text-slate-400'}`}>
                            <Check className="h-4 w-4" />
                          </span>
                        </label>
                      )
                    })}
                  </div>
                </div>
              ) : null}

              <div className="mt-5 grid grid-cols-2 gap-3">
                {/*
                 * Telephone en PREMIER champ : le hook usePhoneLookup va
                 * (Phase 5 etape 1) appeler /client/lookup en debounce 300ms
                 * et auto-prefill prenom + nom si le client est connu. Mettre
                 * le tel d abord cree donc un effet "magique" : la cliente
                 * tape son numero, ses autres champs se remplissent tout seuls.
                 */}
                <label className="col-span-2 block">
                  <span className="flex items-center gap-2 text-[11px] font-black uppercase text-slate-500">
                    Telephone
                    {phoneLookup.state === 'loading' ? (
                      <Loader2 className="h-3 w-3 animate-spin text-[#f31976]" aria-hidden />
                    ) : null}
                    {phoneLookup.state === 'found' && phoneLookup.data?.prenom ? (
                      <span className="flex items-center gap-1 rounded-full bg-[#fff0f6] px-2 py-0.5 text-[10px] font-black normal-case text-[#f31976]">
                        <Check className="h-3 w-3" aria-hidden />
                        Bonjour {phoneLookup.data.prenom} !
                      </span>
                    ) : null}
                  </span>
                  <PhoneInput
                    international
                    defaultCountry="SN"
                    value={bookingForm.telephone || undefined}
                    onChange={(value) => updateBookingField('telephone', value ?? '')}
                    placeholder="77 123 45 67"
                    autoComplete="tel"
                    required
                    numberInputProps={{
                      className:
                        'h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10',
                    }}
                    className="mt-1.5 flex items-center gap-2"
                  />
                </label>
                <label className="block">
                  <span className="text-[11px] font-black uppercase text-slate-500">Prenom</span>
                  <input
                    value={bookingForm.prenom}
                    onChange={(event) => updateBookingField('prenom', event.target.value)}
                    required
                    autoComplete="given-name"
                    className="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                  />
                </label>
                <label className="block">
                  <span className="text-[11px] font-black uppercase text-slate-500">Nom</span>
                  <input
                    value={bookingForm.nom}
                    onChange={(event) => updateBookingField('nom', event.target.value)}
                    required
                    autoComplete="family-name"
                    className="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                  />
                </label>
                <label className="col-span-2 block">
                  <span className="text-[11px] font-black uppercase text-slate-500">Email</span>
                  <input
                    type="email"
                    value={bookingForm.email}
                    onChange={(event) => updateBookingField('email', event.target.value)}
                    autoComplete="email"
                    className="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                  />
                </label>
              </div>

              <div className="mt-5 grid grid-cols-2 gap-3">
                <label className="block">
                  <span className="text-[11px] font-black uppercase text-slate-500">Date</span>
                  <input
                    type="date"
                    min={todayInput()}
                    value={bookingForm.date_reservation}
                    onChange={(event) => {
                      setAvailability(null)
                      setAvailabilityLoading(true)
                      setAvailabilityError(null)
                      updateBookingField('date_reservation', event.target.value)
                    }}
                    required
                    className="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                  />
                </label>
                <label className="block">
                  <span className="text-[11px] font-black uppercase text-slate-500">Code promo</span>
                  <input
                    value={bookingForm.code_promo}
                    onChange={(event) => updateBookingField('code_promo', event.target.value)}
                    placeholder={promotions[0]?.code ?? 'Code'}
                    className="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold uppercase outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                  />
                </label>

                <div className="col-span-2">
                  <div className="flex items-center justify-between gap-3">
                    <span className="text-[11px] font-black uppercase text-slate-500">Horaires</span>
                    {availabilityLoading ? <Loader2 className="h-4 w-4 animate-spin text-[#f31976]" /> : null}
                  </div>
                  <div className="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                    {(availability?.creneaux ?? []).map((slot) => (
                      <button
                        key={slot.heure}
                        type="button"
                        disabled={!slot.disponible}
                        onClick={() => updateBookingField('heure_debut', slot.heure)}
                        className={`min-h-12 rounded-2xl border px-2 text-sm font-black transition disabled:cursor-not-allowed disabled:opacity-45 ${
                          bookingForm.heure_debut === slot.heure
                            ? 'border-[#f31976] bg-[#f31976] text-white'
                            : 'border-slate-200 bg-white text-slate-800'
                        }`}
                      >
                        {slot.heure}
                      </button>
                    ))}
                  </div>
                  {availabilityError ? <p className="mt-2 text-xs font-bold text-rose-600">{availabilityError}</p> : null}
                  {availability?.jour_ferme ? <p className="mt-2 text-xs font-bold text-amber-600">Le salon est ferme ce jour-la.</p> : null}
                  {availability?.jour_complet ? <p className="mt-2 text-xs font-bold text-amber-600">Cette journee est complete.</p> : null}
                </div>
              </div>

              <div className="mt-6">
                <p className="text-sm font-black text-slate-950">Paiement de l acompte</p>
                <div className="mt-3 grid grid-cols-3 gap-2">
                  {paymentMethods.map((method) => {
                    const Icon = method.icon
                    const checked = bookingForm.paymentMethod === method.value
                    const configured =
                      method.value === 'carte_bancaire'
                        ? settings?.paiements_en_ligne?.carte_bancaire !== false
                        : settings?.paiements_en_ligne?.[method.value] !== false

                    return (
                      <label
                        key={method.value}
                        className={`block min-h-[94px] select-none rounded-2xl border px-2 py-3 text-center transition ${
                          !configured
                            ? 'cursor-not-allowed opacity-40'
                            : checked
                              ? 'cursor-pointer border-[#f31976] bg-[#fff0f6]'
                              : 'cursor-pointer border-slate-200 bg-white'
                        }`}
                      >
                        <input
                          type="radio"
                          name="mode_paiement"
                          value={method.value}
                          checked={checked}
                          disabled={!configured}
                          onChange={() => updateBookingField('paymentMethod', method.value)}
                          className="sr-only"
                        />
                        <span className="flex flex-col items-center justify-center gap-1.5 text-xs font-black text-slate-950 sm:flex-row sm:text-sm">
                          {method.logo ? (
                            <img src={method.logo} alt="" className="h-7 w-7 rounded-full object-contain" />
                          ) : (
                            <Icon className="h-5 w-5 text-[#f31976]" />
                          )}
                          <span>
                            <span className="sm:hidden">
                              {method.value === 'orange_money' ? 'Orange' : method.value === 'carte_bancaire' ? 'Carte' : method.label}
                            </span>
                            <span className="hidden sm:inline">{method.label}</span>
                          </span>
                        </span>
                        <span className="mt-2 hidden text-xs font-bold text-slate-500 sm:block">
                          {configured ? method.detail : 'Non disponible'}
                        </span>
                      </label>
                    )
                  })}
                </div>

                {bookingForm.paymentMethod !== 'carte_bancaire' ? (
                  <div className="mt-4 rounded-3xl bg-[#fff0f6] p-4 text-sm font-bold text-[#b01258]">
                    Vous serez redirigee vers PayTech pour payer par {selectedPaymentMethod?.label}. Le paiement sera valide automatiquement par IPN.
                  </div>
                ) : (
                  <div className="mt-4 rounded-3xl bg-[#fff0f6] p-4 text-sm font-bold text-[#b01258]">
                    Vous serez redirigee vers Stripe pour payer par carte bancaire. Le paiement sera valide automatiquement au retour.
                  </div>
                )}
              </div>

              <div className="mt-6 rounded-3xl bg-slate-950 p-5 text-white">
                <div className="flex items-center justify-between gap-4 text-sm font-bold text-white/70">
                  <span>Sous-total</span>
                  <span>{formatCurrency(subtotal, devise)}</span>
                </div>
                <div className="mt-2 flex items-center justify-between gap-4 text-sm font-bold text-white/70">
                  <span>Reduction</span>
                  <span>{formatCurrency(discount, devise)}</span>
                </div>
                <div className="mt-4 flex items-center justify-between gap-4 border-t border-white/10 pt-4">
                  <span className="text-sm font-black">Total estime</span>
                  <span className="text-xl font-black">{formatCurrency(total, devise)}</span>
                </div>
                <div className="mt-2 flex items-center justify-between gap-4 text-sm font-bold text-white/70">
                  <span>Acompte estime</span>
                  <span>{formatCurrency(deposit, devise)}</span>
                </div>
                <div className="mt-2 flex items-center justify-between gap-4 text-sm font-bold text-white/70">
                  <span>Moyen de paiement</span>
                  <span>{selectedPaymentMethod?.label ?? 'Wave'}</span>
                </div>
              </div>

              <label className="mt-5 block">
                <span className="text-[11px] font-black uppercase text-slate-500">Commentaire</span>
                <textarea
                  value={bookingForm.notes}
                  onChange={(event) => updateBookingField('notes', event.target.value)}
                  placeholder="Ex : preference, longueur, disponibilite"
                  rows={3}
                  className="mt-1.5 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                />
              </label>

              {submitState ? (
                <div
                  className={`mt-4 rounded-3xl px-5 py-4 text-sm font-bold ${
                    submitState.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                  }`}
                >
                  {submitState.message}
                  {submittedReservation ? (
                    <p className="mt-2 text-xs">
                      Reservation #{submittedReservation.id} - acompte {formatCurrency(submittedReservation.montant_acompte, devise)}
                    </p>
                  ) : null}
                </div>
              ) : null}

              <button
                type="submit"
                disabled={submitting}
                className="mt-5 flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl bg-[#f31976] px-5 py-4 text-base font-black text-white shadow-lg transition disabled:cursor-not-allowed disabled:opacity-60"
              >
                {submitting ? <Loader2 className="h-5 w-5 animate-spin" /> : <CalendarCheck className="h-5 w-5" />}
                {bookingForm.paymentMethod === 'carte_bancaire' ? 'Continuer vers Stripe' : 'Continuer vers PayTech'}
              </button>
            </form>
          </div>
        </div>
      ) : null}

      {coiffures.length === 0 && !loading && !catalogueError ? (
        <div className="fixed bottom-24 right-4 hidden max-w-sm rounded-3xl bg-white p-5 text-sm font-bold text-slate-600 shadow-xl lg:block">
          <div className="mb-3 grid h-10 w-10 place-items-center rounded-2xl bg-[#fff0f6] text-[#f31976]">
            <ImageIcon className="h-5 w-5" />
          </div>
          Ajoutez des coiffures actives avec variantes et photos dans l admin pour remplir cette page.
        </div>
      ) : null}

    </div>
  )
}

export default ClientHomePage
