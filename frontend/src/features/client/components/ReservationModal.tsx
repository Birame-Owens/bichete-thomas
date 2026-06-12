import axios from 'axios'
import { CalendarCheck, Check, ChevronLeft, ChevronRight, CreditCard, Loader2, Phone, X } from 'lucide-react'
import { type FormEvent, useEffect, useState } from 'react'
import type { LucideIcon } from 'lucide-react'
import PhoneInput from 'react-phone-number-input'
import 'react-phone-number-input/style.css'
import { createClientReservation, getClientAvailability, getClientCoiffureDetails } from '../client.api'
import {
  coiffureImage,
  depositAmount,
  discountAmount,
  formatCurrency,
  formatDuration,
  isClosedDate,
  todayInput,
} from '../client.helpers'
import { usePhoneLookup } from '../hooks/usePhoneLookup'
import type {
  ClientAvailability,
  ClientCatalogue,
  ClientCoiffure,
  ClientCoiffureOption,
  ClientPaymentMethod,
  ClientPromotion,
  ClientReservation,
  ClientReservationPayload,
  ClientSession,
} from '../client.types'

type CatalogueSettings = ClientCatalogue['settings']

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

type SubmitState = { type: 'success' | 'error'; message: string } | null

// Etapes de l'assistant de reservation.
const bookingSteps = ['Prestation', 'Date & heure', 'Paiement'] as const

const paymentMethods: Array<{
  value: ClientPaymentMethod
  label: string
  detail: string
  icon: LucideIcon
  logo?: string
}> = [
  { value: 'wave', label: 'Wave', detail: 'Paiement securise via NabooPay', icon: Phone, logo: '/wave logo.webp' },
  { value: 'orange_money', label: 'Orange Money', detail: 'Paiement securise via NabooPay', icon: Phone, logo: '/om logo.webp' },
  { value: 'carte_bancaire', label: 'Carte bancaire', detail: 'Paiement securise via NabooPay', icon: CreditCard },
]

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
    const payload = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : undefined
    if (firstError) {
      return firstError
    }
    if (payload?.message) {
      return payload.message
    }
  }
  return 'Impossible de finaliser la reservation pour le moment.'
}

type ReservationModalProps = {
  /** Coiffure cliquee (resume). Le modal recupere lui-meme les details complets. */
  coiffure: ClientCoiffure
  settings: CatalogueSettings | undefined
  devise: string
  promotions: ClientPromotion[]
  /** Session client (prefill non destructif tel/nom/prenom). */
  session?: ClientSession | null
  onClose: () => void
}

/**
 * Modal de reservation partage entre la home et la page categorie.
 * Auto-suffisant : recupere les details de la coiffure, gere la galerie, le
 * wizard (Prestation -> Date & heure -> Paiement), la disponibilite, le
 * prefill (tel + session) et la soumission (redirection paiement NabooPay).
 */
function ReservationModal({ coiffure, settings, devise, promotions, session = null, onClose }: ReservationModalProps) {
  const [detail, setDetail] = useState<ClientCoiffure>(coiffure)
  const [modalLoading, setModalLoading] = useState(true)
  const [selectedGalleryImage, setSelectedGalleryImage] = useState<string | null>(() => coiffureImage(coiffure))
  const [bookingForm, setBookingForm] = useState<BookingForm>(() => createBookingForm(coiffure))
  const [bookingStep, setBookingStep] = useState(0)
  const [availability, setAvailability] = useState<ClientAvailability | null>(null)
  const [availabilityLoading, setAvailabilityLoading] = useState(false)
  const [availabilityError, setAvailabilityError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [submitState, setSubmitState] = useState<SubmitState>(null)
  const [submittedReservation, setSubmittedReservation] = useState<ClientReservation | null>(null)

  // Lookup tel international + prefill auto (debounce 300ms, E.164 valide).
  const phoneLookup = usePhoneLookup(bookingForm.telephone)

  // Recupere les details complets (variantes, options, photos) a l'ouverture.
  useEffect(() => {
    let ignore = false
    setModalLoading(true)

    getClientCoiffureDetails(coiffure.id)
      .then((details) => {
        if (ignore) return
        setDetail(details)
        setSelectedGalleryImage(coiffureImage(details))
        setBookingForm((current) => ({
          ...current,
          varianteId: details.variantes[0]?.id ? String(details.variantes[0].id) : current.varianteId,
        }))
      })
      .catch(() => {
        if (!ignore) {
          setSubmitState({ type: 'error', message: 'Impossible de charger tous les details de cette coiffure.' })
        }
      })
      .finally(() => {
        if (!ignore) setModalLoading(false)
      })

    return () => {
      ignore = true
    }
  }, [coiffure.id])

  // Bloque le defilement de la page de fond tant que le modal est ouvert.
  useEffect(() => {
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = previousOverflow
    }
  }, [])

  // Prefill non destructif nom/prenom quand le tel est reconnu.
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

  // Prefill non destructif depuis la session active (ajoute le telephone).
  useEffect(() => {
    if (!session) {
      return
    }
    setBookingForm((current) => ({
      ...current,
      telephone: current.telephone.trim() === '' ? session.telephone : current.telephone,
      prenom: current.prenom.trim() === '' ? session.prenom : current.prenom,
      nom: current.nom.trim() === '' ? session.nom : current.nom,
    }))
  }, [session])

  // Disponibilite : recharge les creneaux a chaque changement de date.
  useEffect(() => {
    if (bookingForm.date_reservation === '') {
      return
    }

    if (isClosedDate(bookingForm.date_reservation, settings)) {
      setAvailability(null)
      setAvailabilityError('Le salon est ferme ce jour-la.')
      setAvailabilityLoading(false)
      setBookingForm((current) => ({ ...current, heure_debut: '' }))
      return
    }

    let ignore = false
    setAvailabilityLoading(true)
    setAvailabilityError(null)

    getClientAvailability(bookingForm.date_reservation, 60)
      .then((data) => {
        if (ignore) return
        setAvailability(data)
        setBookingForm((current) => {
          const currentSlot = data.creneaux.find((slot) => slot.heure === current.heure_debut)
          const firstAvailableSlot = data.creneaux.find((slot) => slot.disponible)
          if (current.heure_debut !== '' && currentSlot?.disponible) {
            return current
          }
          return { ...current, heure_debut: firstAvailableSlot?.heure ?? '' }
        })
      })
      .catch(() => {
        if (!ignore) {
          setAvailabilityError('Les horaires ne sont pas disponibles pour cette date.')
          setAvailability(null)
        }
      })
      .finally(() => {
        if (!ignore) setAvailabilityLoading(false)
      })

    return () => {
      ignore = true
    }
  }, [bookingForm.date_reservation, settings])

  function updateBookingField<K extends keyof BookingForm>(key: K, value: BookingForm[K]) {
    setBookingForm((current) => ({ ...current, [key]: value }))
  }

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

  const selectedVariant = detail.variantes.find((variant) => String(variant.id) === bookingForm.varianteId)
  const selectedOptions = detail.options.filter((option) => bookingForm.optionIds.includes(option.id))
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

  const detailGalleryImages =
    detail.images.length > 0
      ? detail.images
      : [{ id: 0, url: coiffureImage(detail), alt: detail.nom, principale: true }]

  const lastBookingStep = bookingSteps.length - 1

  const canContinueBooking =
    bookingStep === 0
      ? bookingForm.varianteId !== ''
      : bookingStep === 1
        ? bookingForm.date_reservation !== ''
          && !isClosedDate(bookingForm.date_reservation, settings)
          && !(availability?.jour_ferme ?? false)
          && bookingForm.heure_debut !== ''
        : true

  const goNextBookingStep = () => {
    setSubmitState(null)
    setBookingStep((current) => Math.min(current + 1, lastBookingStep))
  }

  const goPrevBookingStep = () => {
    setSubmitState(null)
    setBookingStep((current) => Math.max(current - 1, 0))
  }

  async function handleReservationSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    // Tant qu'on n'est pas a la derniere etape, "Entree" fait avancer
    // l'assistant (si l'etape est valide) au lieu de soumettre.
    if (bookingStep < lastBookingStep) {
      if (canContinueBooking) {
        goNextBookingStep()
      }
      return
    }

    if (!selectedVariant) {
      setSubmitState({ type: 'error', message: 'Choisissez une variante.' })
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

    const payload: ClientReservationPayload = {
      client: {
        nom: bookingForm.nom.trim(),
        prenom: bookingForm.prenom.trim(),
        telephone: bookingForm.telephone.trim(),
        email: bookingForm.email.trim() === '' ? null : bookingForm.email.trim(),
      },
      coiffure_id: detail.id,
      variante_coiffure_id: selectedVariant.id,
      option_ids: bookingForm.optionIds,
      date_reservation: bookingForm.date_reservation,
      heure_debut: bookingForm.heure_debut,
      code_promo: bookingForm.code_promo.trim() === '' ? null : bookingForm.code_promo.trim(),
      notes: bookingForm.notes.trim() === '' ? null : bookingForm.notes.trim(),
      mode_paiement: bookingForm.paymentMethod,
      idempotency_key: [
        'client-reservation',
        detail.id,
        selectedVariant.id,
        bookingForm.telephone.trim(),
        bookingForm.date_reservation,
        bookingForm.heure_debut,
        bookingForm.paymentMethod,
      ].join('|'),
      reference_paiement: null,
      success_url: `${window.location.origin}${window.location.pathname}?paiement=naboopay_success`,
      cancel_url: `${window.location.origin}${window.location.pathname}?paiement=naboopay_cancel`,
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

  const mainImage = selectedGalleryImage ?? coiffureImage(detail)

  return (
    <div className="bt-overlay-in fixed inset-0 z-40 flex items-end justify-center bg-slate-950/65 backdrop-blur-sm sm:items-center sm:p-6">
      <div className="bt-sheet-in flex h-[100dvh] w-full max-w-7xl flex-col overflow-hidden rounded-none bg-white shadow-2xl sm:h-auto sm:max-h-[92vh] sm:rounded-3xl">
        {/* En-tete collant : categorie + nom + fermeture toujours visibles. */}
        <div className="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 bg-white px-4 py-3 sm:px-6 sm:py-4">
          <div className="min-w-0">
            <p className="truncate text-[11px] font-black uppercase tracking-[0.14em] text-[#f31976]">
              {detail.categorie?.nom ?? 'Coiffure'}
            </p>
            <h2 className="truncate text-lg font-black text-slate-950 sm:text-2xl">{detail.nom}</h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-950 transition hover:bg-slate-200 active:scale-95"
            aria-label="Fermer"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Corps scrollable : image + infos a gauche, formulaire a droite. */}
        <div className="grid min-h-0 flex-1 gap-0 overflow-y-auto lg:grid-cols-[0.92fr_1.08fr]">
          <section className="bg-[#fff7fb] p-4 sm:p-6">
            <div className="relative block aspect-[4/3] w-full overflow-hidden rounded-2xl bg-slate-100 text-left">
              {/* Fond flou de la meme photo : comble le vide d'object-contain. */}
              <img src={mainImage} alt="" aria-hidden className="absolute inset-0 h-full w-full scale-110 object-cover opacity-55 blur-2xl" />
              <img src={mainImage} alt={detail.nom} className="relative h-full w-full object-contain" loading="lazy" />
              {modalLoading ? (
                <div className="absolute inset-0 grid place-items-center bg-white/70">
                  <Loader2 className="h-8 w-8 animate-spin text-[#f31976]" />
                </div>
              ) : null}
            </div>

            <div className="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
              {detailGalleryImages.map((image) => (
                <button
                  key={`${image.id}-${image.url}`}
                  type="button"
                  onClick={() => setSelectedGalleryImage(image.url)}
                  className={`aspect-square overflow-hidden bg-white ring-offset-2 transition ${
                    mainImage === image.url ? 'ring-2 ring-[#f31976]' : 'hover:ring-2 hover:ring-[#f31976]/30'
                  }`}
                  aria-label="Afficher cette photo"
                >
                  <img src={image.url} alt={image.alt ?? ''} className="h-full w-full object-cover object-[center_18%]" loading="lazy" />
                </button>
              ))}
            </div>

            <p className="mt-5 text-sm font-semibold leading-7 text-slate-600">
              {detail.description ?? 'Une prestation soignée, adaptée à votre style et au temps disponible au salon.'}
            </p>

            <div className="mt-5 grid grid-cols-2 gap-3">
              <div className="bg-white p-4">
                <p className="text-xs font-black uppercase text-slate-400">Prix</p>
                <p className="mt-1 text-lg font-black text-slate-950">{formatCurrency(detail.prix_min, devise)}</p>
              </div>
              <div className="bg-white p-4">
                <p className="text-xs font-black uppercase text-slate-400">Duree</p>
                <p className="mt-1 text-lg font-black text-slate-950">{formatDuration(detail.duree_min_minutes)}</p>
              </div>
            </div>
          </section>

          <form onSubmit={handleReservationSubmit} className="flex min-h-full flex-col p-4 sm:p-6">
            {/* En-tete + barre de progression de l'assistant. */}
            <div>
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-black text-[#f31976]">Reservation</p>
                  <h3 className="mt-0.5 text-xl font-black text-slate-950 sm:text-2xl">
                    Etape {bookingStep + 1} · {bookingSteps[bookingStep]}
                  </h3>
                </div>
                <span className="shrink-0 rounded-full bg-[#fff0f6] px-3 py-1 text-xs font-black text-[#d80f63]">
                  {bookingStep + 1}/{bookingSteps.length}
                </span>
              </div>
              <div className="mt-3 flex gap-1.5">
                {bookingSteps.map((label, index) => (
                  <span
                    key={label}
                    className={`h-1.5 flex-1 rounded-full transition-colors duration-500 ${index <= bookingStep ? 'bg-[#f31976]' : 'bg-slate-200'}`}
                  />
                ))}
              </div>
            </div>

            <div key={bookingStep} className="bt-step-in mt-5 flex-1">
              {/* ETAPE 1 — Prestation : variante + options */}
              {bookingStep === 0 ? (
                <div className="space-y-6">
                  <div>
                    <p className="text-sm font-black text-slate-950">Variante</p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                      {detail.variantes.map((variant) => (
                        <label
                          key={variant.id}
                          className={`block cursor-pointer select-none rounded-3xl border p-4 text-left transition ${
                            bookingForm.varianteId === String(variant.id) ? 'border-[#f31976] bg-[#fff0f6]' : 'border-slate-200 bg-white'
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

                  {detail.options.length > 0 ? (
                    <div>
                      <p className="text-sm font-black text-slate-950">Options</p>
                      <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        {detail.options.map((option) => {
                          const checked = bookingForm.optionIds.includes(option.id)
                          return (
                            <label
                              key={option.id}
                              className={`flex cursor-pointer select-none items-center justify-between gap-3 rounded-3xl border p-4 text-left transition ${
                                checked ? 'border-[#f31976] bg-[#fff0f6]' : 'border-slate-200 bg-white'
                              }`}
                            >
                              <input type="checkbox" checked={checked} onChange={() => toggleOption(option)} className="sr-only" />
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
                </div>
              ) : null}

              {/* ETAPE 2 — Date & heure */}
              {bookingStep === 1 ? (
                <div className="space-y-5">
                  <label className="block">
                    <span className="text-[11px] font-black uppercase text-slate-500">Date du rendez-vous</span>
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
                      className="mt-1.5 h-12 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                    />
                  </label>
                  {bookingForm.date_reservation !== '' && isClosedDate(bookingForm.date_reservation, settings) ? (
                    <p className="rounded-2xl bg-amber-50 px-4 py-3 text-xs font-bold text-amber-700">
                      Le salon est ferme ce jour-la. Choisissez une autre date.
                    </p>
                  ) : (
                    <p className="text-xs font-semibold text-slate-500">Choisissez le jour, puis l'heure ci-dessous.</p>
                  )}
                  <div>
                    <div className="mb-2 flex items-center justify-between gap-3">
                      <span className="text-[11px] font-black uppercase text-slate-500">Choisissez votre heure</span>
                      {availabilityLoading ? <Loader2 className="h-4 w-4 animate-spin text-[#f31976]" /> : null}
                    </div>
                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                      {(availability?.creneaux ?? []).map((slot) => (
                        <button
                          key={slot.heure}
                          type="button"
                          disabled={!slot.disponible}
                          onClick={() => updateBookingField('heure_debut', slot.heure)}
                          className={`min-h-12 rounded-2xl border px-2 text-sm font-black transition disabled:cursor-not-allowed disabled:opacity-45 ${
                            bookingForm.heure_debut === slot.heure ? 'border-[#f31976] bg-[#f31976] text-white' : 'border-slate-200 bg-white text-slate-800'
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
              ) : null}

              {/* ETAPE 3 — Coordonnees & paiement */}
              {bookingStep === 2 ? (
                <div className="space-y-5">
                  <div className="grid grid-cols-2 gap-3">
                    <label className="col-span-2 block">
                      <span className="flex items-center gap-2 text-[11px] font-black uppercase text-slate-500">
                        Telephone
                        {phoneLookup.state === 'loading' ? <Loader2 className="h-3 w-3 animate-spin text-[#f31976]" aria-hidden /> : null}
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
                          className: 'h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10',
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
                    <label className="col-span-2 block">
                      <span className="text-[11px] font-black uppercase text-slate-500">Code promo (optionnel)</span>
                      <input
                        value={bookingForm.code_promo}
                        onChange={(event) => updateBookingField('code_promo', event.target.value)}
                        placeholder={promotions[0]?.code ?? 'Code'}
                        className="mt-1.5 h-11 w-full rounded-2xl border border-slate-200 px-3 text-sm font-bold uppercase outline-none focus:border-[#f31976] focus:ring-4 focus:ring-[#f31976]/10"
                      />
                    </label>
                  </div>

                  <div>
                    <p className="text-sm font-black text-slate-950">Paiement de l'acompte</p>
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
                              !configured ? 'cursor-not-allowed opacity-40' : checked ? 'cursor-pointer border-[#f31976] bg-[#fff0f6]' : 'cursor-pointer border-slate-200 bg-white'
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
                  </div>

                  <div className="rounded-3xl bg-slate-950 p-5 text-white">
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

                  <label className="block">
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
                    <div className={`rounded-3xl px-5 py-4 text-sm font-bold ${submitState.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                      {submitState.message}
                      {submittedReservation ? (
                        <p className="mt-2 text-xs">
                          Reservation #{submittedReservation.id} - acompte {formatCurrency(submittedReservation.montant_acompte, devise)}
                        </p>
                      ) : null}
                    </div>
                  ) : null}
                </div>
              ) : null}
            </div>

            {/* Navigation de l'assistant (collante en bas). */}
            <div className="sticky bottom-0 -mx-4 mt-5 flex gap-3 border-t border-slate-100 bg-white/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
              {bookingStep > 0 ? (
                <button
                  type="button"
                  onClick={goPrevBookingStep}
                  className="inline-flex min-h-14 items-center justify-center gap-1 rounded-2xl border border-slate-200 px-5 text-sm font-black text-slate-700 transition hover:bg-slate-50"
                >
                  <ChevronLeft className="h-5 w-5" />
                  Retour
                </button>
              ) : null}
              {bookingStep < lastBookingStep ? (
                <button
                  type="button"
                  onClick={goNextBookingStep}
                  disabled={!canContinueBooking}
                  className="flex min-h-14 flex-1 items-center justify-center gap-2 rounded-2xl bg-[#f31976] px-5 text-base font-black text-white shadow-lg transition hover:bg-[#d6165e] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Continuer
                  <ChevronRight className="h-5 w-5" />
                </button>
              ) : (
                <button
                  type="submit"
                  disabled={submitting}
                  className="flex min-h-14 flex-1 items-center justify-center gap-2 rounded-2xl bg-[#f31976] px-5 text-base font-black text-white shadow-lg transition hover:bg-[#d6165e] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {submitting ? <Loader2 className="h-5 w-5 animate-spin" /> : <CalendarCheck className="h-5 w-5" />}
                  Confirmer et payer l'acompte
                </button>
              )}
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default ReservationModal
