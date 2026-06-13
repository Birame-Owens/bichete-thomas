import axios from 'axios'
import {
  Bell,
  CalendarCheck,
  Check,
  CheckCircle,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Gift,
  Home,
  Image as ImageIcon,
  Loader2,
  LogOut,
  MapPin,
  MessageCircle,
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
import {
  confirmPaytechReturn,
  confirmNaboopayReturn,
  confirmStripeCheckout,
  getClientCatalogue,
  getClientSession,
  logoutClientSession,
  registerClient,
  requestClientLogin,
  verifyMagicLink,
} from './client.api'
import {
  closedDaysLabel,
  formatCurrency,
  formatShortDate,
  promoText,
} from './client.helpers'
import { CoiffureCard } from './components/CoiffureCard'
import Reveal from './components/Reveal'
import ReservationModal from './components/ReservationModal'
import PromoPopup from './PromoPopup'
import { useSeoPage } from '../../hooks/useSeoPage'
import type {
  ClientCatalogue,
  ClientCategory,
  ClientCoiffure,
  ClientPaymentWithRelations,
  ClientPromotion,
  ClientSession,
} from './client.types'

type SubmitState = {
  type: 'success' | 'error'
  message: string
} | null

type ClientAuthMode = 'login' | 'register'

type ClientAuthForm = {
  prenom: string
  nom: string
  telephone: string
  email: string
}

type ClientNavItem = {
  id: string
  label: string
  icon: LucideIcon
}

const clientNavItems: ClientNavItem[] = [
  { id: 'accueil', label: 'Accueil', icon: Home },
  { id: 'galerie', label: 'Galerie', icon: ImageIcon },
  { id: 'apropos', label: 'A propos', icon: Users },
  { id: 'contact', label: 'Contact', icon: MessageCircle },
]

const benefits: Array<{ label: string; detail: string; icon: LucideIcon }> = [
  { label: 'Diagnostic', detail: 'Conseil avant pose', icon: MessageCircle },
  { label: 'Finition', detail: 'Details soignes', icon: Sparkles },
  { label: 'Planning', detail: 'Créneaux visibles', icon: CalendarCheck },
  { label: 'Paiement', detail: 'Acompte sécurisé', icon: ShieldCheck },
]

const instagramUrl = 'https://www.instagram.com/bichette_thomas/'
const tiktokUrl = 'https://www.tiktok.com/@bichette_thomas'
const mapsSearchQuery = 'Bichette Thomas salon coiffure Dakar Senegal'
const mapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(mapsSearchQuery)}`
const mapsEmbedUrl = `https://maps.google.com/maps?q=${encodeURIComponent(mapsSearchQuery)}&z=15&output=embed`

// Galerie d ambiance du salon. Les visuels vivent dans frontend/public et
// sont servis statiquement (aucun appel API). Modifier ce tableau suffit pour
// changer les photos mises en avant sur la home.
const salonGallery: Array<{ src: string; titre: string; sousTitre: string }> = [
  { src: '/b1.jpg', titre: 'Notre univers', sousTitre: 'Une ambiance chaleureuse et soignee' },
  { src: '/b2.jpg', titre: 'Le savoir-faire', sousTitre: 'Des poses precises, des finitions nettes' },
  { src: '/b3.jpg', titre: 'Vos resultats', sousTitre: 'Des coiffures qui vous subliment' },
]

const emptyCategories: ClientCategory[] = []
const emptyCoiffures: ClientCoiffure[] = []
const emptyPromotions: ClientPromotion[] = []

// Les helpers de formatage / calcul / dates sont externalises dans
// ./client.helpers.ts pour permettre le memo correct des sous-composants
// (CoiffureCard, etc.). Les composants RatingStars et CoiffureCard sont
// dans ./components/ et memoizes (I11).

function createClientAuthForm(session?: ClientSession | null): ClientAuthForm {
  return {
    prenom: session?.prenom ?? '',
    nom: session?.nom ?? '',
    telephone: session?.telephone ?? '',
    email: '',
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
  useSeoPage('accueil')
  const [catalogue, setCatalogue] = useState<ClientCatalogue | null>(null)
  const [loading, setLoading] = useState(true)
  const [catalogueError, setCatalogueError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [favoriteIds, setFavoriteIds] = useState<number[]>([])
  const [selectedCoiffure, setSelectedCoiffure] = useState<ClientCoiffure | null>(null)
  const [pageNotice, setPageNotice] = useState<SubmitState>(null)
  const [clientSession, setClientSession] = useState<ClientSession | null>(null)
  const [showClientAuth, setShowClientAuth] = useState(false)
  const [clientAuthMode, setClientAuthMode] = useState<ClientAuthMode>('login')
  const [clientAuthForm, setClientAuthForm] = useState<ClientAuthForm>(() => createClientAuthForm())
  const [clientAuthSubmitting, setClientAuthSubmitting] = useState(false)
  const [clientAuthNotice, setClientAuthNotice] = useState<SubmitState>(null)
  const [paymentConfirmation, setPaymentConfirmation] = useState<ClientPaymentWithRelations | null>(null)
  const [paymentConfirming, setPaymentConfirming] = useState(false)
  // Index de la photo galerie ouverte en grand (lightbox). null = fermee.
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null)
  // Navbar : ombre/elevation des qu'on a defile, et section actuellement lue
  // (scroll-spy) pour souligner le lien correspondant.
  const [navScrolled, setNavScrolled] = useState(false)
  const [activeSection, setActiveSection] = useState('accueil')

  // Verifie silencieusement si un cookie de session valide existe deja
  // (401 = pas de session = silence). Sert au prefill du modal de reservation.
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

      setPaymentConfirming(true)
      confirmPaytechReturn(paymentId, signature)
        .then((response) => {
          setPaymentConfirmation(response.data)
          window.history.replaceState({}, '', window.location.pathname)
        })
        .catch(() => {
          setPageNotice({
            type: 'success',
            message: 'Paiement PayTech recu. La confirmation automatique sera appliquee par notification IPN.',
          })
          window.history.replaceState({}, '', window.location.pathname)
        })
        .finally(() => setPaymentConfirming(false))
      return
    }

    if (paymentStatus === 'naboopay_cancel') {
      window.setTimeout(() => {
        setPageNotice({ type: 'error', message: 'Paiement NabooPay annule. Le creneau ne sera pas confirme.' })
      }, 0)
      return
    }

    if (paymentStatus === 'naboopay_success') {
      const paymentId = params.get('paiement_id')
      const signature = params.get('signature')

      if (!paymentId || !signature) {
        window.setTimeout(() => {
          setPageNotice({
            type: 'success',
            message: 'Retour NabooPay recu. La confirmation sera appliquee par webhook.',
          })
          window.history.replaceState({}, '', window.location.pathname)
        }, 0)
        return
      }

      setPaymentConfirming(true)
      confirmNaboopayReturn(paymentId, signature)
        .then((response) => {
          setPaymentConfirmation(response.data)
          window.history.replaceState({}, '', window.location.pathname)
        })
        .catch(() => {
          setPageNotice({
            type: 'success',
            message: 'Retour NabooPay recu. La confirmation sera appliquee par webhook.',
          })
          window.history.replaceState({}, '', window.location.pathname)
        })
        .finally(() => setPaymentConfirming(false))
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

  // Navbar : ombre/elevation des qu'on quitte le tout-en-haut de la page.
  useEffect(() => {
    const onScroll = () => setNavScrolled(window.scrollY > 8)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  // Scroll-spy : souligne le lien de la section actuellement au centre de
  // l'ecran. rootMargin cree une fine bande au milieu du viewport ; la section
  // qui la traverse devient active. Re-arme apres chargement du catalogue
  // (les sections existent alors avec leur hauteur definitive).
  useEffect(() => {
    const ids = clientNavItems.map((item) => item.id)
    const elements = ids
      .map((id) => document.getElementById(id))
      .filter((element): element is HTMLElement => element !== null)

    if (elements.length === 0 || typeof IntersectionObserver === 'undefined') {
      return
    }

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)
        if (visible[0]) {
          setActiveSection(visible[0].target.id)
        }
      },
      { rootMargin: '-45% 0px -50% 0px', threshold: [0, 0.25, 0.5, 1] },
    )

    elements.forEach((element) => observer.observe(element))
    return () => observer.disconnect()
  }, [loading])

  // Galerie : photos de la base (gerees par l'admin). Si aucune n'est encore
  // uploadee, on retombe sur les 3 visuels statiques pour ne pas laisser la
  // section vide.
  const galleryItems = useMemo(() => {
    const fromDb = (catalogue?.gallery ?? []).map((photo) => ({
      src: photo.url,
      titre: photo.titre ?? 'Bichette Thomas',
      sousTitre: photo.sous_titre ?? '',
    }))
    return fromDb.length > 0 ? fromDb : salonGallery
  }, [catalogue?.gallery])

  // Navigation clavier de la lightbox galerie (Echap, fleches gauche/droite).
  useEffect(() => {
    if (lightboxIndex === null) {
      return
    }
    const count = galleryItems.length
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setLightboxIndex(null)
      } else if (event.key === 'ArrowRight') {
        setLightboxIndex((current) => (current === null ? current : (current + 1) % count))
      } else if (event.key === 'ArrowLeft') {
        setLightboxIndex((current) => (current === null ? current : (current - 1 + count) % count))
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [lightboxIndex, galleryItems])

  const categories = catalogue?.categories ?? emptyCategories
  const coiffures = catalogue?.coiffures ?? emptyCoiffures
  const promotions = catalogue?.promotions ?? emptyPromotions
  const devise = settings?.devise ?? 'FCFA'

  const popularCoiffures = useMemo(() => {
    const query = search.trim().toLowerCase()

    return coiffures.filter((coiffure) => {
      if (!coiffure.est_populaire) {
        return false
      }

      const matchesSearch =
        query === ''
        || coiffure.nom.toLowerCase().includes(query)
        || (coiffure.description ?? '').toLowerCase().includes(query)
        || (coiffure.categorie?.nom ?? '').toLowerCase().includes(query)

      return matchesSearch
    }).slice(0, 6)
  }, [coiffures, search])

  const homeVisibleCoiffures = useMemo(() => {
    const query = search.trim().toLowerCase()

    return coiffures.filter((coiffure) => {
      if (!coiffure.est_nouveaute || coiffure.est_populaire) {
        return false
      }

      const matchesSearch =
        query === ''
        || coiffure.nom.toLowerCase().includes(query)
        || (coiffure.description ?? '').toLowerCase().includes(query)
        || (coiffure.categorie?.nom ?? '').toLowerCase().includes(query)

      return matchesSearch
    }).slice(0, 6)
  }, [coiffures, search])

  const fallbackHomeCoiffures = useMemo(() => {
    if (popularCoiffures.length > 0 || homeVisibleCoiffures.length > 0) {
      return []
    }

    const query = search.trim().toLowerCase()

    return coiffures.filter((coiffure) => {
      const matchesSearch =
        query === ''
        || coiffure.nom.toLowerCase().includes(query)
        || (coiffure.description ?? '').toLowerCase().includes(query)
        || (coiffure.categorie?.nom ?? '').toLowerCase().includes(query)

      return matchesSearch
    }).slice(0, 6)
  }, [coiffures, homeVisibleCoiffures.length, popularCoiffures.length, search])

  function updateClientAuthField<K extends keyof ClientAuthForm>(key: K, value: ClientAuthForm[K]) {
    setClientAuthForm((current) => ({
      ...current,
      [key]: value,
    }))
  }

  async function handleClientAuthSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setClientAuthSubmitting(true)
    setClientAuthNotice(null)

    try {
      const response = clientAuthMode === 'login'
        ? await requestClientLogin(clientAuthForm.telephone.trim())
        : await registerClient({
            prenom: clientAuthForm.prenom.trim(),
            nom: clientAuthForm.nom.trim(),
            telephone: clientAuthForm.telephone.trim(),
            email: clientAuthForm.email.trim() === '' ? null : clientAuthForm.email.trim(),
          })

      setClientAuthNotice({
        type: 'success',
        message: response.debug_magic_url
          ? `${response.message} Lien dev : ${response.debug_magic_url}`
          : response.message,
      })
    } catch (error) {
      setClientAuthNotice({ type: 'error', message: extractApiError(error) })
    } finally {
      setClientAuthSubmitting(false)
    }
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

  async function handleLogout() {
    try {
      await logoutClientSession()
    } finally {
      setClientSession(null)
    }
  }

  // Ouvre le modal de reservation. Le composant ReservationModal recupere
  // lui-meme les details complets de la coiffure.
  const openDetails = useCallback((coiffure: ClientCoiffure) => {
    setSelectedCoiffure(coiffure)
  }, [])

  return (
    <div className="min-h-screen bg-[#fdfafd] text-slate-950">

      <PromoPopup />

      {/* Spinner pendant la vérification du paiement côté backend */}
      {paymentConfirming && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
          <div className="flex flex-col items-center gap-4 rounded-3xl bg-white px-12 py-10 shadow-2xl">
            <Loader2 className="h-10 w-10 animate-spin text-[#f31976]" />
            <p className="text-sm font-black text-slate-700">Vérification du paiement...</p>
          </div>
        </div>
      )}

      {/* Écran de confirmation "trust payment" affiché après retour PSP */}
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

      <header
        id="accueil"
        className={`bt-navbar-in fixed top-0 left-0 right-0 z-30 backdrop-blur transition-all duration-300 ${
          navScrolled
            ? 'border-b border-[#f7d6e5] bg-white/95 shadow-[0_12px_30px_-22px_rgba(20,20,43,0.6)]'
            : 'border-b border-transparent bg-white/80'
        }`}
      >
        <div className="mx-auto w-full max-w-[1440px] px-3 py-2 sm:px-5 lg:px-8">
          <div className="flex items-center gap-2 lg:grid lg:grid-cols-[auto_1fr_auto] lg:gap-3">
            <a href="/" className="group flex shrink-0 items-center gap-3">
              <img
                src="/logo-bichette.jpg"
                alt="Bichette Thomas"
                className="h-11 w-11 shrink-0 rounded-2xl object-cover object-center transition-transform duration-300 group-hover:scale-105 sm:h-12 sm:w-12"
              />
              <div className="hidden min-w-0 sm:block">
                <p className="font-display text-xl leading-5 text-slate-950 sm:text-2xl">
                  Bichette <span className="text-[#f31976]">Thomas</span>
                </p>
                <p className="mt-1 flex items-center gap-1 text-[11px] font-bold text-slate-500">
                  <MapPin className="h-3.5 w-3.5 text-[#f31976]" />
                  Dakar, Senegal
                </p>
              </div>
            </a>

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
              {clientNavItems.map((item) => {
                const Icon = item.icon
                const active = activeSection === item.id

                return (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => scrollToSection(item.id)}
                    className={`group relative inline-flex h-10 shrink-0 items-center gap-2 px-2.5 text-[10px] font-black uppercase tracking-[0.13em] transition-colors duration-300 hover:text-[#f31976] sm:px-3 sm:text-xs ${
                      active ? 'text-[#f31976]' : 'text-slate-600'
                    }`}
                  >
                    <Icon className="h-4 w-4" />
                    {item.label}
                    {/* Soulignement rose qui se deploie de gauche a droite
                        (au survol, ou en continu sur la section active). */}
                    <span
                      className={`pointer-events-none absolute inset-x-2.5 bottom-1 h-0.5 origin-left rounded-full bg-[#f31976] transition-transform duration-300 ease-out group-hover:scale-x-100 sm:inset-x-3 ${
                        active ? 'scale-x-100' : 'scale-x-0'
                      }`}
                    />
                  </button>
                )
              })}
              </nav>
              <button
                type="button"
                onClick={() => clientSession ? handleLogout() : setPageNotice({ type: 'error', message: 'Espace client pas encore disponible.' })}
                className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#fff0f6] text-[#f31976] shadow-sm transition hover:scale-105 active:scale-95 sm:hidden"
                aria-label="Profil client"
              >
                <User className="h-5 w-5" />
              </button>
              <button
                type="button"
                className="hidden h-11 w-11 shrink-0 place-items-center rounded-full border border-slate-200 bg-white text-slate-800 shadow-sm transition hover:scale-105 hover:text-[#f31976] active:scale-95 sm:grid"
                aria-label="Notifications"
              >
                <Bell className="h-5 w-5" />
              </button>
              {clientSession ? (
                <button
                  type="button"
                  onClick={handleLogout}
                  title="Se deconnecter"
                  className="flex h-11 shrink-0 items-center gap-2 rounded-full bg-[#fff0f6] px-3 text-sm font-black text-[#f31976] transition hover:scale-105 active:scale-95"
                >
                  <User className="h-4 w-4" />
                  <span className="hidden sm:inline">{clientSession.prenom}</span>
                  <LogOut className="h-4 w-4" />
                </button>
              ) : (
                <button
                  type="button"
                  onClick={() => setPageNotice({ type: 'error', message: 'Espace client pas encore disponible.' })}
                  className="hidden h-11 w-11 shrink-0 place-items-center rounded-full bg-[#fff0f6] text-[#f31976] transition hover:scale-105 active:scale-95 sm:grid"
                  aria-label="Profil client"
                >
                  <User className="h-5 w-5" />
                </button>
              )}
            </div>
          </div>
        </div>
      </header>

      {/* Spacer compensant la hauteur du header fixed (~60px) */}
      <div className="h-[60px]" />

      {pageNotice ? (
        <div className="mx-auto w-full max-w-[1440px] px-3 pt-3 sm:px-5 lg:px-8">
          <div
            className={`mb-5 rounded-3xl px-5 py-4 text-sm font-bold ${
              pageNotice.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
            }`}
          >
            {pageNotice.message}
          </div>
        </div>
      ) : null}

      <section className="relative overflow-hidden bg-[#f31976] text-white">
            <div className="absolute inset-0">
              {settings?.image_accueil ? (
                // Image de garde definie par l'admin : prioritaire sur la video.
                <img
                  src={settings.image_accueil}
                  alt=""
                  className="bt-kenburns h-full w-full object-cover object-center opacity-95"
                />
              ) : (
                <>
                  <video
                    className="hidden h-full w-full object-cover object-center md:block"
                    src="/video acceuil.MP4"
                    autoPlay
                    muted
                    loop
                    playsInline
                    preload="metadata"
                  />
                  <img
                    src="/image mobile.jpg"
                    alt=""
                    className="bt-kenburns h-full w-full object-cover object-[55%_center] opacity-90 md:hidden"
                    loading="lazy"
                  />
                </>
              )}
              <div className="absolute inset-0 hidden bg-gradient-to-r from-black/60 via-[#f31976]/15 to-transparent md:block" />
              <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent md:hidden" />
            </div>
            <div className="relative flex min-h-[430px] items-end p-5 sm:min-h-[560px] sm:p-10 lg:min-h-[620px] lg:p-14">
              <div className="pb-4 md:hidden">
                <p className="bt-animate-fade-up text-[9px] font-black uppercase tracking-[0.28em] text-white/80" style={{ animationDelay: '0.05s' }}>
                  Salon de coiffure à Dakar
                </p>
                <h1 className="font-display bt-animate-fade-up mt-3 text-3xl leading-tight text-white" style={{ animationDelay: '0.18s' }}>
                  Bichette Thomas
                </h1>
                <button
                  type="button"
                  onClick={() => scrollToSection('catalogue')}
                  className="bt-soft-pop mt-5 inline-flex min-h-11 items-center gap-2 bg-white px-5 text-xs font-black uppercase tracking-[0.18em] text-[#d80f63] transition hover:scale-105 hover:bg-[#fff0f6] active:scale-95"
                  style={{ animationDelay: '0.5s' }}
                >
                  Choisir une coiffure
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
              <div className="hidden max-w-3xl pb-8 md:block">
                <p className="bt-animate-fade-up inline-flex border border-white/40 px-3 py-1 text-[9px] font-black uppercase tracking-[0.28em] text-white/85 backdrop-blur sm:text-[10px]" style={{ animationDelay: '0.05s' }}>
                  Salon de coiffure à Dakar
                </p>
                <h1 className="font-display bt-animate-fade-up mt-5 max-w-2xl text-4xl leading-none text-white sm:text-6xl lg:text-7xl" style={{ animationDelay: '0.18s' }}>
                  Bichette Thomas
                </h1>
                <p className="bt-animate-fade-up mt-5 max-w-xl text-sm font-semibold leading-6 text-white/90 sm:text-base sm:leading-7" style={{ animationDelay: '0.3s' }}>
                  Coiffures protectrices, poses soignées et réservations simples, avec une touche rose signature et un service pensé pour votre rythme.
                </p>
                <button
                  type="button"
                  onClick={() => scrollToSection('catalogue')}
                  className="bt-soft-pop mt-7 inline-flex min-h-12 items-center gap-2 bg-white px-6 text-xs font-black uppercase tracking-[0.2em] text-[#d80f63] shadow-sm transition hover:scale-105 hover:bg-[#fff0f6] active:scale-95"
                  style={{ animationDelay: '0.6s' }}
                >
                  Choisir une coiffure
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>

              {/* Indicateur de defilement discret : invite a descendre. */}
              <button
                type="button"
                onClick={() => scrollToSection('categories')}
                aria-label="Faire defiler vers le bas"
                className="absolute bottom-4 left-1/2 hidden -translate-x-1/2 flex-col items-center gap-1 text-white/80 transition hover:text-white sm:flex"
              >
                <span className="text-[9px] font-black uppercase tracking-[0.3em]">Decouvrir</span>
                <span className="grid h-9 w-9 place-items-center rounded-full border border-white/40">
                  <ChevronDown className="bt-scroll-hint h-4 w-4" />
                </span>
              </button>
            </div>
      </section>

      <div className="mx-auto w-full max-w-[1440px] px-3 pb-12 sm:px-5 lg:px-8">
        <main>
          {catalogueError ? (
            <div className="mt-5 rounded-3xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-700">
              {catalogueError}
            </div>
          ) : null}

          <section id="categories" className="border-b border-[#f7d6e5] bg-white py-7">
            <div className="flex items-center justify-between gap-3 px-1">
              <h2 className="text-xl font-light uppercase tracking-[0.18em] text-slate-950">Categories</h2>
              <div className="text-right text-sm font-bold text-slate-500">
                <p>{settings?.heure_ouverture ?? '09:00'} - {settings?.heure_fermeture ?? '19:00'}</p>
                <p className="text-xs font-semibold text-slate-400">
                  {closedDaysLabel(settings?.jours_fermeture ?? [])}
                </p>
              </div>
            </div>

            <div className="mt-5 flex gap-8 overflow-x-auto px-1 pb-3 sm:gap-10 lg:justify-center">
              <button
                type="button"
                onClick={() => window.location.assign('/categories')}
                className="group flex min-w-[82px] flex-col items-center gap-3"
              >
                <span
                  className="grid h-20 w-20 place-items-center rounded-full border-2 border-transparent bg-[#fff0f6] text-xs font-black uppercase tracking-[0.14em] text-[#f31976] transition group-hover:border-[#f31976] sm:h-24 sm:w-24"
                >
                  Tout
                </span>
                <span className="w-24 truncate text-center text-[11px] font-black uppercase tracking-[0.18em] text-slate-600">
                  Tous styles
                </span>
              </button>
              {categories.map((category) => (
                <button
                  key={category.id}
                  type="button"
                  onClick={() => window.location.assign(`/categories/${category.id}`)}
                  className="group flex min-w-[82px] flex-col items-center gap-3"
                >
                  <span
                    className="h-20 w-20 overflow-hidden rounded-full border-2 border-transparent bg-[#fff0f6] transition group-hover:border-[#f31976] sm:h-24 sm:w-24"
                  >
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

          <section id="catalogue" className="py-12">
            <div className="flex items-center justify-end">
              <button
                type="button"
                onClick={() => {
                  window.location.assign('/categories')
                }}
                className="border-b border-[#f31976] pb-1 text-xs font-black uppercase tracking-[0.18em] text-[#f31976]"
              >
                Voir le catalogue
              </button>
            </div>

            {loading ? (
              <div className="mt-8 grid grid-cols-2 gap-x-3 gap-y-9 sm:gap-x-5 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                {Array.from({ length: 6 }, (_, index) => (
                  <div key={index} className="h-60 animate-pulse bg-white" />
                ))}
              </div>
            ) : (
              <div className="space-y-16">
                {popularCoiffures.length > 0 ? (
                  <div>
                    <Reveal className="text-center">
                      <h2 className="text-4xl font-light uppercase tracking-[0.24em] text-slate-950">Populaires</h2>
                      <p className="mt-3 text-sm font-semibold italic text-slate-500">Les coiffures les plus demandées au salon</p>
                    </Reveal>
                    <div className="mt-9 grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-6 lg:gap-y-10 xl:grid-cols-6">
                      {popularCoiffures.map((coiffure, index) => (
                        <Reveal key={coiffure.id} delay={Math.min(index * 80, 400)}>
                          <CoiffureCard
                            coiffure={coiffure}
                            isFavorite={favoriteIds.includes(coiffure.id)}
                            devise={devise}
                            onToggleFavorite={toggleFavorite}
                            onOpenDetails={openDetails}
                          />
                        </Reveal>
                      ))}
                    </div>
                  </div>
                ) : null}

                {homeVisibleCoiffures.length > 0 ? (
                  <div>
                    <Reveal className="text-center">
                      <h2 className="text-4xl font-light uppercase tracking-[0.24em] text-slate-950">A la une</h2>
                      <p className="mt-3 text-sm font-semibold italic text-slate-500">Sélection visible sur la page d'accueil</p>
                    </Reveal>
                    <div className="mt-9 grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-6 lg:gap-y-10 xl:grid-cols-6">
                      {homeVisibleCoiffures.map((coiffure, index) => (
                        <Reveal key={coiffure.id} delay={Math.min(index * 80, 400)}>
                          <CoiffureCard
                            coiffure={coiffure}
                            isFavorite={favoriteIds.includes(coiffure.id)}
                            devise={devise}
                            onToggleFavorite={toggleFavorite}
                            onOpenDetails={openDetails}
                          />
                        </Reveal>
                      ))}
                    </div>
                  </div>
                ) : null}

                {fallbackHomeCoiffures.length > 0 ? (
                  <div>
                    <Reveal className="text-center">
                      <h2 className="text-4xl font-light uppercase tracking-[0.24em] text-slate-950">Selection</h2>
                      <p className="mt-3 text-sm font-semibold italic text-slate-500">Quelques coiffures du catalogue</p>
                    </Reveal>
                    <div className="mt-9 grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-3 lg:grid-cols-4 lg:gap-x-6 lg:gap-y-10 xl:grid-cols-6">
                      {fallbackHomeCoiffures.map((coiffure, index) => (
                        <Reveal key={coiffure.id} delay={Math.min(index * 80, 400)}>
                          <CoiffureCard
                            coiffure={coiffure}
                            isFavorite={favoriteIds.includes(coiffure.id)}
                            devise={devise}
                            onToggleFavorite={toggleFavorite}
                            onOpenDetails={openDetails}
                          />
                        </Reveal>
                      ))}
                    </div>
                  </div>
                ) : null}
              </div>
            )}
          </section>

          <section id="promos" className="py-10">
            {promotions.length > 0 ? (
              <div className="grid gap-4 lg:grid-cols-2">
                {promotions.map((promo) => (
                  <article key={promo.id} className="flex items-center justify-between gap-4 bg-[#1a1116] p-6 text-white">
                    <div>
                      <p className="text-[10px] font-black uppercase tracking-[0.32em] text-white/50">Offre limitee</p>
                      <p className="mt-3 text-3xl font-light uppercase tracking-[0.12em] text-white">{promoText(promo)} réservation</p>
                      <p className="mt-3 text-sm font-semibold text-white/70">
                        Code : <span className="font-black text-white">{promo.code}</span>
                      </p>
                    </div>
                    <div className="grid h-16 w-16 shrink-0 place-items-center bg-white text-[#f31976]">
                      <Gift className="h-9 w-9" />
                    </div>
                  </article>
                ))}
              </div>
            ) : (
              <div className="bg-white p-5 text-sm font-bold text-slate-500">
                Les promotions actives apparaitront ici.
              </div>
            )}
          </section>

          <section id="apropos" className="grid gap-7 border-y border-[#f7d6e5] bg-white py-12 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
            <Reveal className="group min-h-[360px] overflow-hidden rounded-3xl bg-[#fff0f6]">
              <img src="/image mobile.jpg" alt="" className="h-full min-h-[360px] w-full object-cover object-[55%_center] transition duration-[1100ms] ease-out group-hover:scale-110" loading="lazy" />
            </Reveal>
            <Reveal delay={120} className="px-1 lg:px-8">
              <p className="text-[10px] font-black uppercase tracking-[0.32em] text-[#f31976]">A propos de nous</p>
              <h2 className="font-display mt-4 text-4xl leading-tight text-slate-950 sm:text-5xl">
                Un salon pensé pour sublimer chaque coiffure, du choix au rendez-vous.
              </h2>
              <p className="mt-5 max-w-2xl text-sm font-semibold leading-7 text-slate-600 sm:text-base">
                Bichette Thomas accompagne les clientes avec des coiffures élégantes, protectrices et adaptées au quotidien. La réservation en ligne garde l'expérience simple : vous voyez les styles, les prix, les durées et les horaires avant de confirmer.
              </p>
              <div className="mt-7 grid grid-cols-3 gap-3 text-center">
                <div className="border border-[#f7d6e5] px-3 py-4">
                  <p className="text-2xl font-black text-[#f31976]">{categories.length}</p>
                  <p className="mt-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Categories</p>
                </div>
                <div className="border border-[#f7d6e5] px-3 py-4">
                  <p className="text-2xl font-black text-[#f31976]">{coiffures.length}</p>
                  <p className="mt-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Coiffures</p>
                </div>
                <div className="border border-[#f7d6e5] px-3 py-4">
                  <p className="text-2xl font-black text-[#f31976]">{settings?.limite_reservations_par_creneau ?? 3}</p>
                  <p className="mt-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Par creneau</p>
                </div>
              </div>
            </Reveal>
          </section>

          <section id="galerie" className="py-12">
            <Reveal className="text-center">
              <p className="text-[10px] font-black uppercase tracking-[0.32em] text-[#f31976]">Le salon en images</p>
              <h2 className="font-display mt-3 text-4xl leading-tight text-slate-950 sm:text-5xl">
                Plongez dans l'univers Bichette Thomas
              </h2>
              <p className="mx-auto mt-4 max-w-2xl text-sm font-semibold leading-7 text-slate-500">
                Ambiance, savoir-faire et resultats : quelques instants captures au salon.
              </p>
            </Reveal>

            {/* Mosaique masonry (colonnes CSS) : hauteurs variables = rendu
                dynamique facon mur de photos. Jusqu'a 10 visuels geres en admin. */}
            <div className="mt-9 columns-2 gap-4 sm:columns-3 lg:columns-4">
              {galleryItems.map((item, index) => (
                <Reveal
                  key={`${item.src}-${index}`}
                  delay={Math.min(index * 90, 540)}
                  className="group relative mb-4 block break-inside-avoid overflow-hidden rounded-3xl bg-[#fff0f6] shadow-sm transition-shadow duration-500 hover:shadow-[0_26px_46px_-24px_rgba(243,25,118,0.55)]"
                >
                  <img
                    src={item.src}
                    alt={item.titre}
                    className="w-full transition duration-[1100ms] ease-out group-hover:scale-110"
                    loading="lazy"
                  />
                  <div className="absolute inset-0 bg-gradient-to-t from-black/65 via-black/5 to-transparent opacity-90 transition duration-500 group-hover:opacity-100" />
                  <div className="absolute inset-x-0 bottom-0 translate-y-2 p-5 opacity-0 transition duration-500 group-hover:translate-y-0 group-hover:opacity-100">
                    <p className="text-[10px] font-black uppercase tracking-[0.24em] text-white/80">{item.titre}</p>
                    {item.sousTitre ? <p className="mt-1 text-lg font-black leading-snug text-white">{item.sousTitre}</p> : null}
                  </div>
                  <span className="absolute left-4 top-4 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-[#f31976] shadow-sm transition duration-500 group-hover:scale-110 group-hover:rotate-12">
                    <Sparkles className="h-4 w-4" />
                  </span>
                  <button
                    type="button"
                    onClick={() => setLightboxIndex(index)}
                    aria-label={`Agrandir la photo : ${item.titre}`}
                    className="absolute inset-0 z-10 cursor-zoom-in"
                  />
                </Reveal>
              ))}
            </div>

            <Reveal delay={120} className="mt-6 flex justify-center">
              <button
                type="button"
                onClick={() => scrollToSection('catalogue')}
                className="inline-flex min-h-12 items-center gap-2 rounded-full bg-[#f31976] px-7 text-xs font-black uppercase tracking-[0.2em] text-white shadow-lg transition hover:scale-105 hover:bg-[#d6165e] active:scale-95"
              >
                Reserver ma coiffure
                <ChevronRight className="h-4 w-4" />
              </button>
            </Reveal>
          </section>

          <section id="reservations" className="mt-10 grid gap-5 xl:grid-cols-[1fr_360px]">
            <div>
              <p className="text-[10px] font-black uppercase tracking-[0.32em] text-[#f31976]">Experience</p>
              <h2 className="mt-2 text-3xl font-light uppercase tracking-[0.14em] text-slate-950">Pourquoi nous choisir ?</h2>
              <div className="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                {benefits.map((benefit) => {
                  const Icon = benefit.icon

                  return (
                    <article key={benefit.label} className="group rounded-2xl bg-white p-5 text-center shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                      <div className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-[#fff0f6] text-[#f31976] transition duration-300 group-hover:scale-110 group-hover:bg-[#f31976] group-hover:text-white">
                        <Icon className="h-6 w-6" />
                      </div>
                      <p className="mt-3 text-sm font-black text-slate-950">{benefit.label}</p>
                      <p className="mt-1 text-xs font-bold text-slate-500">{benefit.detail}</p>
                    </article>
                  )
                })}
              </div>
            </div>

            <aside id="contact" className="bg-white p-5">
              <p className="text-lg font-black text-slate-950">Réservation rapide</p>
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
                      <img src="/instagram.svg" alt="Instagram" className="h-4 w-4" />
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
                      <img src="/tiktok.svg" alt="TikTok" className="h-4 w-4" />
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
        <ReservationModal
          coiffure={selectedCoiffure}
          settings={settings}
          devise={devise}
          promotions={promotions}
          session={clientSession}
          onClose={() => setSelectedCoiffure(null)}
        />
      ) : null}

      {showClientAuth ? (
        <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/55 px-3 py-6 backdrop-blur-sm">
          <div className="w-full max-w-lg overflow-hidden rounded-[28px] bg-white shadow-2xl">
            <div className="flex items-start justify-between gap-4 border-b border-slate-100 p-5">
              <div>
                <p className="text-sm font-black text-[#f31976]">Espace client</p>
                <h2 className="mt-1 text-2xl font-black text-slate-950">
                  {clientAuthMode === 'login' ? 'Connexion' : 'Inscription'}
                </h2>
              </div>
              <button
                type="button"
                onClick={() => setShowClientAuth(false)}
                className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-slate-50 text-slate-950"
                aria-label="Fermer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-5">
              <div className="grid grid-cols-2 gap-2 rounded-2xl bg-slate-100 p-1">
                {(['login', 'register'] as ClientAuthMode[]).map((mode) => (
                  <button
                    key={mode}
                    type="button"
                    onClick={() => {
                      setClientAuthMode(mode)
                      setClientAuthNotice(null)
                    }}
                    className={`min-h-10 rounded-xl px-3 text-sm font-black transition ${
                      clientAuthMode === mode ? 'bg-white text-[#f31976] shadow-sm' : 'text-slate-500'
                    }`}
                  >
                    {mode === 'login' ? 'Connexion' : 'Inscription'}
                  </button>
                ))}
              </div>

              <form onSubmit={handleClientAuthSubmit} className="mt-5 grid grid-cols-2 gap-3">
                {clientAuthMode === 'register' ? (
                  <>
                    <label className="block">
                      <span className="text-[11px] font-black uppercase text-slate-500">Prenom</span>
                      <input
                        value={clientAuthForm.prenom}
                        onChange={(event) => updateClientAuthField('prenom', event.target.value)}
                        required
                        autoComplete="given-name"
                        className="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                      />
                    </label>
                    <label className="block">
                      <span className="text-[11px] font-black uppercase text-slate-500">Nom</span>
                      <input
                        value={clientAuthForm.nom}
                        onChange={(event) => updateClientAuthField('nom', event.target.value)}
                        required
                        autoComplete="family-name"
                        className="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                      />
                    </label>
                  </>
                ) : null}

                <label className="col-span-2 block">
                  <span className="text-[11px] font-black uppercase text-slate-500">Telephone WhatsApp</span>
                  <PhoneInput
                    international
                    defaultCountry="SN"
                    value={clientAuthForm.telephone || undefined}
                    onChange={(value) => updateClientAuthField('telephone', value ?? '')}
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

                {clientAuthMode === 'register' ? (
                  <label className="col-span-2 block">
                    <span className="text-[11px] font-black uppercase text-slate-500">Email</span>
                    <input
                      type="email"
                      value={clientAuthForm.email}
                      onChange={(event) => updateClientAuthField('email', event.target.value)}
                      autoComplete="email"
                      className="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                    />
                  </label>
                ) : null}

                {clientAuthNotice ? (
                  <div
                    className={`col-span-2 rounded-3xl px-4 py-3 text-sm font-bold ${
                      clientAuthNotice.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                    }`}
                  >
                    {clientAuthNotice.message}
                  </div>
                ) : null}

                <button
                  type="submit"
                  disabled={clientAuthSubmitting}
                  className="col-span-2 mt-1 flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-[#f31976] px-5 text-sm font-black text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {clientAuthSubmitting ? <Loader2 className="h-5 w-5 animate-spin" /> : <MessageCircle className="h-5 w-5" />}
                  Recevoir le lien WhatsApp
                </button>
              </form>
            </div>
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

      {/* Lightbox galerie : vue agrandie + navigation fluide entre les photos. */}
      {lightboxIndex !== null ? (
        <div
          className="bt-overlay-in fixed inset-0 z-[60] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm"
          onClick={() => setLightboxIndex(null)}
          role="dialog"
          aria-modal="true"
        >
          <button
            type="button"
            onClick={() => setLightboxIndex(null)}
            aria-label="Fermer"
            className="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full bg-white/15 text-white transition hover:bg-white/25"
          >
            <X className="h-5 w-5" />
          </button>
          <button
            type="button"
            onClick={(event) => {
              event.stopPropagation()
              setLightboxIndex((current) =>
                current === null ? current : (current - 1 + galleryItems.length) % galleryItems.length,
              )
            }}
            aria-label="Photo precedente"
            className="absolute left-3 grid h-11 w-11 place-items-center rounded-full bg-white/15 text-white transition hover:bg-white/25 sm:left-6"
          >
            <ChevronLeft className="h-6 w-6" />
          </button>
          <figure className="flex max-h-[88vh] max-w-3xl flex-col items-center" onClick={(event) => event.stopPropagation()}>
            <img
              key={lightboxIndex}
              src={galleryItems[lightboxIndex].src}
              alt={galleryItems[lightboxIndex].titre}
              className="bt-zoom-in max-h-[80vh] w-auto max-w-full rounded-2xl object-contain shadow-2xl"
            />
            <figcaption className="mt-4 text-center">
              <p className="text-[10px] font-black uppercase tracking-[0.24em] text-white/70">
                {galleryItems[lightboxIndex].titre}
              </p>
              {galleryItems[lightboxIndex].sousTitre ? (
                <p className="mt-1 text-sm font-bold text-white">{galleryItems[lightboxIndex].sousTitre}</p>
              ) : null}
            </figcaption>
          </figure>
          <button
            type="button"
            onClick={(event) => {
              event.stopPropagation()
              setLightboxIndex((current) => (current === null ? current : (current + 1) % galleryItems.length))
            }}
            aria-label="Photo suivante"
            className="absolute right-3 grid h-11 w-11 place-items-center rounded-full bg-white/15 text-white transition hover:bg-white/25 sm:right-6"
          >
            <ChevronRight className="h-6 w-6" />
          </button>
        </div>
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

export default ClientHomePage
