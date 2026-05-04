import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import {
  CalendarDays,
  Clock3,
  Edit,
  Eye,
  Plus,
  RefreshCw,
  Search,
  Scissors,
  Trash2,
  UserRound,
  WalletCards,
  X,
} from 'lucide-react'
import AdminLayout from '../../../layouts/AdminLayout'
import {
  createReservation,
  deleteReservation,
  getReservation,
  getReservationLookups,
  getReservations,
  updateReservation,
  updateReservationStatus,
} from './reservations.api'
import {
  EmptyState,
  ErrorState,
  FormField,
  Modal,
  Pagination,
  SuccessState,
  dangerButtonClass,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from './ReservationsUi'
import type {
  Coiffure,
  LaravelPaginated,
  Reservation,
  ReservationDetailForm,
  ReservationForm,
  ReservationLookups,
  ReservationStatus,
  VarianteCoiffure,
} from './reservations.types'

const statusOptions: Array<{ value: ReservationStatus; label: string }> = [
  { value: 'en_attente', label: 'En attente' },
  { value: 'confirmee', label: 'Confirmee' },
  { value: 'acompte_paye', label: 'Acompte paye' },
  { value: 'en_cours', label: 'En cours' },
  { value: 'terminee', label: 'Terminee' },
  { value: 'annulee', label: 'Annulee' },
  { value: 'absence', label: 'Absence' },
]

const emptyDetail: ReservationDetailForm = {
  coiffure_id: '',
  variante_coiffure_id: '',
  quantite: '1',
  option_ids: [],
}

const emptyForm: ReservationForm = {
  client_id: '',
  coiffeuse_id: '',
  date_reservation: new Date().toISOString().slice(0, 10),
  heure_debut: '09:00',
  statut: 'en_attente',
  source: 'admin',
  code_promo_id: '',
  regle_fidelite_id: '',
  montant_acompte: '',
  notes: '',
  details: [emptyDetail],
}

const emptyLookups: ReservationLookups = {
  clients: [],
  coiffeuses: [],
  coiffures: [],
  codesPromo: [],
  reglesFidelite: [],
}

function numberValue(value: number | string | null | undefined) {
  return Number(value || 0)
}

function money(value: number | string | null | undefined, currency = 'FCFA') {
  return `${numberValue(value).toLocaleString('fr-FR')} ${currency}`
}

function minutesLabel(minutes: number) {
  if (minutes < 60) {
    return `${minutes} min`
  }

  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`
}

function statusLabel(status: ReservationStatus) {
  return statusOptions.find((item) => item.value === status)?.label ?? status
}

function statusClass(status: ReservationStatus) {
  if (status === 'terminee') {
    return 'bg-emerald-50 text-emerald-700'
  }

  if (status === 'annulee' || status === 'absence') {
    return 'bg-red-50 text-red-700'
  }

  if (status === 'acompte_paye' || status === 'en_cours') {
    return 'bg-[#fff2f7] text-[#c41468]'
  }

  return 'bg-gray-100 text-gray-600'
}

function clientName(reservation: Reservation) {
  if (!reservation.client) {
    return 'Client supprime'
  }

  return `${reservation.client.prenom} ${reservation.client.nom}`.trim()
}

function coiffeuseName(reservation: Reservation) {
  if (!reservation.coiffeuse) {
    return 'Non assignee'
  }

  return `${reservation.coiffeuse.prenom} ${reservation.coiffeuse.nom}`.trim()
}

function dateInput(value: string) {
  return value.slice(0, 10)
}

function formatDate(value: string) {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

function reservationToForm(reservation: Reservation): ReservationForm {
  return {
    client_id: reservation.client_id ? String(reservation.client_id) : '',
    coiffeuse_id: reservation.coiffeuse_id ? String(reservation.coiffeuse_id) : '',
    date_reservation: dateInput(reservation.date_reservation),
    heure_debut: reservation.heure_debut.slice(0, 5),
    statut: reservation.statut,
    source: reservation.source,
    code_promo_id: reservation.code_promo_id ? String(reservation.code_promo_id) : '',
    regle_fidelite_id: reservation.regle_fidelite_id ? String(reservation.regle_fidelite_id) : '',
    montant_acompte: String(reservation.montant_acompte ?? ''),
    notes: reservation.notes ?? '',
    details: reservation.details?.map((detail) => ({
      coiffure_id: detail.coiffure_id ? String(detail.coiffure_id) : '',
      variante_coiffure_id: detail.variante_coiffure_id ? String(detail.variante_coiffure_id) : '',
      quantite: String(detail.quantite || 1),
      option_ids: detail.option_ids ?? [],
    })) ?? [emptyDetail],
  }
}

function activeVariantes(coiffure?: Coiffure): VarianteCoiffure[] {
  return coiffure?.variantes?.filter((variante) => variante.actif) ?? []
}

function ReservationsPage() {
  const [items, setItems] = useState<LaravelPaginated<Reservation> | null>(null)
  const [lookups, setLookups] = useState<ReservationLookups>(emptyLookups)
  const [form, setForm] = useState<ReservationForm>(emptyForm)
  const [editing, setEditing] = useState<Reservation | null>(null)
  const [detail, setDetail] = useState<Reservation | null>(null)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [coiffeuseFilter, setCoiffeuseFilter] = useState('all')
  const [dateFrom, setDateFrom] = useState(new Date().toISOString().slice(0, 10))
  const [dateTo, setDateTo] = useState('')
  const [loading, setLoading] = useState(true)
  const [lookupsLoading, setLookupsLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const filtersReady = useRef(false)

  const reservations = useMemo(() => items?.data ?? [], [items])
  const today = new Date().toISOString().slice(0, 10)
  const todayCount = useMemo(
    () => reservations.filter((reservation) => dateInput(reservation.date_reservation) === today).length,
    [reservations, today],
  )
  const confirmedCount = useMemo(
    () => reservations.filter((reservation) => ['confirmee', 'acompte_paye', 'en_cours'].includes(reservation.statut)).length,
    [reservations],
  )
  const waitingCount = useMemo(
    () => reservations.filter((reservation) => reservation.statut === 'en_attente').length,
    [reservations],
  )
  const turnoverOnPage = useMemo(
    () => reservations.reduce((total, reservation) => total + numberValue(reservation.montant_total), 0),
    [reservations],
  )

  const preview = useMemo(() => {
    let subtotal = 0
    let duration = 0

    form.details.forEach((detail) => {
      const coiffure = lookups.coiffures.find((item) => String(item.id) === detail.coiffure_id)
      const variante = coiffure?.variantes?.find((item) => String(item.id) === detail.variante_coiffure_id)
      const quantity = Number(detail.quantite || 1)
      const options = coiffure?.options?.filter((option) => detail.option_ids.includes(option.id)) ?? []
      const optionAmount = options.reduce((total, option) => total + numberValue(option.prix), 0)

      if (variante) {
        subtotal += (numberValue(variante.prix) + optionAmount) * quantity
        duration += variante.duree_minutes * quantity
      }
    })

    return {
      subtotal,
      duration,
    }
  }, [form.details, lookups.coiffures])

  const loadPage = useCallback(async (nextPage: number, nextSearch: string, nextStatus: string, nextCoiffeuse: string, nextFrom: string, nextTo: string) => {
    setLoading(true)
    setError(null)
    try {
      setItems(
        await getReservations({
          page: nextPage,
          per_page: 12,
          search: nextSearch || undefined,
          statut: nextStatus === 'all' ? undefined : nextStatus,
          coiffeuse_id: nextCoiffeuse === 'all' ? undefined : nextCoiffeuse,
          date_from: nextFrom || undefined,
          date_to: nextTo || undefined,
        }),
      )
      setPage(nextPage)
    } catch {
      setError('Impossible de charger les reservations.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    getReservations({ page: 1, per_page: 12, date_from: today })
      .then((response) => {
        if (!cancelled) {
          setItems(response)
          setPage(1)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError('Impossible de charger les reservations.')
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    getReservationLookups()
      .then((response) => {
        if (!cancelled) {
          setLookups(response)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError('Impossible de charger les donnees de reservation.')
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLookupsLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [today])

  useEffect(() => {
    if (!filtersReady.current) {
      filtersReady.current = true
      return
    }

    const timeoutId = window.setTimeout(() => {
      void loadPage(1, search, statusFilter, coiffeuseFilter, dateFrom, dateTo)
    }, 300)

    return () => window.clearTimeout(timeoutId)
  }, [coiffeuseFilter, dateFrom, dateTo, loadPage, search, statusFilter])

  const resetForm = () => {
    setForm(emptyForm)
    setEditing(null)
    setModalOpen(false)
  }

  const openModal = (reservation?: Reservation) => {
    setError(null)
    setSuccess(null)
    if (reservation) {
      setEditing(reservation)
      setForm(reservationToForm(reservation))
    } else {
      setEditing(null)
      setForm(emptyForm)
    }
    setModalOpen(true)
  }

  const openDetail = async (reservation: Reservation) => {
    setError(null)
    setSuccess(null)
    setDetail(reservation)
    setDetailModalOpen(true)
    setDetailLoading(true)
    try {
      setDetail(await getReservation(reservation.id))
    } catch {
      setError('Impossible de charger la fiche reservation.')
    } finally {
      setDetailLoading(false)
    }
  }

  const validateForm = () => {
    if (!form.client_id) {
      return 'Selectionnez un client.'
    }

    if (!form.date_reservation || !form.heure_debut) {
      return 'La date et l heure de debut sont obligatoires.'
    }

    if (form.details.length === 0) {
      return 'Ajoutez au moins une prestation.'
    }

    if (form.details.some((detail) => !detail.coiffure_id || !detail.variante_coiffure_id)) {
      return 'Chaque prestation doit avoir une coiffure et une variante.'
    }

    if (form.details.some((detail) => Number(detail.quantite || 0) < 1)) {
      return 'La quantite doit etre positive.'
    }

    return null
  }

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    const validationError = validateForm()
    if (validationError) {
      setError(validationError)
      return
    }

    const isEditing = editing !== null
    setSaving(true)
    try {
      if (editing) {
        await updateReservation(editing.id, form)
      } else {
        await createReservation(form)
      }
      resetForm()
      setSuccess(isEditing ? 'Reservation mise a jour.' : 'Reservation creee.')
      await loadPage(1, search, statusFilter, coiffeuseFilter, dateFrom, dateTo)
    } catch {
      setError('Enregistrement impossible. Verifiez le planning, le client et les prestations.')
    } finally {
      setSaving(false)
    }
  }

  const changeStatus = async (reservation: Reservation, status: ReservationStatus) => {
    try {
      await updateReservationStatus(reservation.id, status, reservation.notes)
      await loadPage(page, search, statusFilter, coiffeuseFilter, dateFrom, dateTo)
      if (detail?.id === reservation.id) {
        setDetail(await getReservation(reservation.id))
      }
    } catch {
      setError('Changement de statut impossible.')
    }
  }

  const remove = async (reservation: Reservation) => {
    if (!window.confirm(`Supprimer la reservation #${reservation.id} ?`)) {
      return
    }

    try {
      await deleteReservation(reservation.id)
      setSuccess('Reservation supprimee.')
      await loadPage(page, search, statusFilter, coiffeuseFilter, dateFrom, dateTo)
    } catch {
      setError('Suppression impossible pour cette reservation.')
    }
  }

  const updateDetail = (index: number, nextDetail: ReservationDetailForm) => {
    setForm((current) => ({
      ...current,
      details: current.details.map((detail, detailIndex) => (detailIndex === index ? nextDetail : detail)),
    }))
  }

  const addDetail = () => {
    setForm((current) => ({
      ...current,
      details: [...current.details, { ...emptyDetail }],
    }))
  }

  const removeDetail = (index: number) => {
    setForm((current) => ({
      ...current,
      details: current.details.filter((_, detailIndex) => detailIndex !== index),
    }))
  }

  return (
    <AdminLayout>
      <div className="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#e91e63]">
            Module reservations
          </p>
          <h1 className="mt-2 text-2xl font-black text-[#111018] sm:text-3xl">Reservations</h1>
          <p className="mt-2 max-w-3xl text-sm font-medium text-gray-500">
            Planning, clientes, coiffeuses, prestations, acomptes et statuts operationnels.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <button
            type="button"
            onClick={() => void loadPage(page, search, statusFilter, coiffeuseFilter, dateFrom, dateTo)}
            className={`${secondaryButtonClass} inline-flex items-center justify-center gap-2`}
          >
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            Actualiser
          </button>
          <button
            type="button"
            onClick={() => openModal()}
            disabled={lookupsLoading}
            className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}
          >
            <Plus className="h-4 w-4" />
            Nouvelle reservation
          </button>
        </div>
      </div>

      <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Aujourd hui</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{todayCount}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Sur cette page</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Confirmees</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{confirmedCount}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Inclut acompte et en cours</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">En attente</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{waitingCount}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">A confirmer</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Montant page</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{money(turnoverOnPage)}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Apres remises</p>
        </div>
      </section>

      <section className="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="grid gap-3 xl:grid-cols-[minmax(220px,1fr)_auto] xl:items-center">
          <div className="relative w-full">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
              placeholder="Rechercher client, telephone, note, numero..."
            />
          </div>
          <div className="grid gap-2 sm:grid-cols-2 xl:flex">
            <input className={inputClass} type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
            <input className={inputClass} type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
            <select className={inputClass} value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
              <option value="all">Tous statuts</option>
              {statusOptions.map((status) => (
                <option key={status.value} value={status.value}>{status.label}</option>
              ))}
            </select>
            <select className={inputClass} value={coiffeuseFilter} onChange={(event) => setCoiffeuseFilter(event.target.value)}>
              <option value="all">Toutes coiffeuses</option>
              {lookups.coiffeuses.map((coiffeuse) => (
                <option key={coiffeuse.id} value={coiffeuse.id}>{coiffeuse.prenom} {coiffeuse.nom}</option>
              ))}
            </select>
          </div>
        </div>
      </section>

      {error && <div className="mb-5"><ErrorState label={error} /></div>}
      {success && <div className="mb-5"><SuccessState label={success} /></div>}

      <section className="grid gap-3 lg:hidden">
        {loading ? (
          Array.from({ length: 4 }).map((_, index) => (
            <article key={index} className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
              <div className="h-5 w-2/3 animate-pulse rounded bg-gray-100" />
              <div className="mt-3 h-4 w-full animate-pulse rounded bg-gray-100" />
              <div className="mt-3 h-4 w-1/2 animate-pulse rounded bg-gray-100" />
            </article>
          ))
        ) : reservations.length === 0 ? (
          <EmptyState label="Aucune reservation trouvee." />
        ) : (
          reservations.map((reservation) => (
            <article key={reservation.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.55)]">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <h2 className="truncate text-base font-black text-gray-950">{clientName(reservation)}</h2>
                  <p className="mt-1 text-sm font-semibold text-gray-500">
                    {formatDate(reservation.date_reservation)} - {reservation.heure_debut.slice(0, 5)}
                  </p>
                </div>
                <span className={`shrink-0 rounded-full px-3 py-1 text-xs font-black ${statusClass(reservation.statut)}`}>
                  {statusLabel(reservation.statut)}
                </span>
              </div>
              <div className="mt-4 grid gap-2 text-sm font-bold text-gray-500">
                <span className="inline-flex items-center gap-2"><Scissors className="h-4 w-4 text-[#e91e63]" />{coiffeuseName(reservation)}</span>
                <span className="inline-flex items-center gap-2"><Clock3 className="h-4 w-4 text-gray-400" />{minutesLabel(reservation.duree_totale_minutes)}</span>
                <span className="inline-flex items-center gap-2 text-[#c41468]"><WalletCards className="h-4 w-4" />{money(reservation.montant_total, reservation.devise)}</span>
              </div>
              <div className="mt-4 flex flex-wrap justify-end gap-1">
                <button type="button" onClick={() => void openDetail(reservation)} className="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100" title="Fiche">
                  <Eye className="h-4 w-4" />
                </button>
                <button type="button" onClick={() => openModal(reservation)} className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                  <Edit className="h-4 w-4" />
                </button>
                <button type="button" onClick={() => void remove(reservation)} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Supprimer">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </article>
          ))
        )}
      </section>

      <section className="hidden overflow-hidden rounded-xl border border-gray-100 bg-white shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)] lg:block">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1080px] text-left text-sm">
            <thead className="bg-gray-50 text-xs font-black uppercase tracking-[0.12em] text-gray-500">
              <tr>
                <th className="px-5 py-3">Date</th>
                <th className="px-5 py-3">Client</th>
                <th className="px-5 py-3">Coiffeuse</th>
                <th className="px-5 py-3">Duree</th>
                <th className="px-5 py-3">Montants</th>
                <th className="px-5 py-3">Statut</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                Array.from({ length: 5 }).map((_, row) => (
                  <tr key={row}>
                    {Array.from({ length: 7 }).map((__, cell) => (
                      <td key={cell} className="px-5 py-4"><div className="h-5 animate-pulse rounded bg-gray-100" /></td>
                    ))}
                  </tr>
                ))
              ) : reservations.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-8">
                    <EmptyState label="Aucune reservation trouvee." />
                  </td>
                </tr>
              ) : (
                reservations.map((reservation) => (
                  <tr key={reservation.id} className="transition hover:bg-[#fff8fb]">
                    <td className="px-5 py-4">
                      <div className="font-black text-gray-950">{formatDate(reservation.date_reservation)}</div>
                      <div className="text-xs font-bold text-gray-400">
                        {reservation.heure_debut.slice(0, 5)} - {reservation.heure_fin.slice(0, 5)}
                      </div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="font-black text-gray-950">{clientName(reservation)}</div>
                      <div className="text-xs font-bold text-gray-400">#{reservation.id}</div>
                    </td>
                    <td className="px-5 py-4 font-semibold text-gray-500">{coiffeuseName(reservation)}</td>
                    <td className="px-5 py-4 font-semibold text-gray-500">{minutesLabel(reservation.duree_totale_minutes)}</td>
                    <td className="px-5 py-4">
                      <div className="font-black text-[#c41468]">{money(reservation.montant_total, reservation.devise)}</div>
                      <div className="text-xs font-bold text-gray-400">Acompte {money(reservation.montant_acompte, reservation.devise)}</div>
                    </td>
                    <td className="px-5 py-4">
                      <select
                        className={`rounded-lg border-0 px-3 py-1 text-xs font-black outline-none ${statusClass(reservation.statut)}`}
                        value={reservation.statut}
                        onChange={(event) => void changeStatus(reservation, event.target.value as ReservationStatus)}
                      >
                        {statusOptions.map((status) => (
                          <option key={status.value} value={status.value}>{status.label}</option>
                        ))}
                      </select>
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex justify-end gap-1">
                        <button type="button" onClick={() => void openDetail(reservation)} className="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100" title="Fiche">
                          <Eye className="h-4 w-4" />
                        </button>
                        <button type="button" onClick={() => openModal(reservation)} className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                          <Edit className="h-4 w-4" />
                        </button>
                        <button type="button" onClick={() => void remove(reservation)} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Supprimer">
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      {items && (
        <Pagination
          page={page}
          lastPage={items.last_page}
          total={items.total}
          onPrevious={() => void loadPage(page - 1, search, statusFilter, coiffeuseFilter, dateFrom, dateTo)}
          onNext={() => void loadPage(page + 1, search, statusFilter, coiffeuseFilter, dateFrom, dateTo)}
        />
      )}

      {modalOpen && (
        <Modal title={editing ? 'Modifier reservation' : 'Nouvelle reservation'} onClose={resetForm} wide>
          <form onSubmit={submit} className="space-y-5">
            <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
              <section className="space-y-4 rounded-xl bg-gray-50 p-4">
                <div className="grid gap-4 sm:grid-cols-2">
                  <FormField label="Client">
                    <select className={inputClass} value={form.client_id} onChange={(event) => setForm((current) => ({ ...current, client_id: event.target.value }))} required>
                      <option value="">Selectionner</option>
                      {lookups.clients.map((client) => (
                        <option key={client.id} value={client.id}>{client.prenom} {client.nom} - {client.telephone}</option>
                      ))}
                    </select>
                  </FormField>
                  <FormField label="Coiffeuse">
                    <select className={inputClass} value={form.coiffeuse_id} onChange={(event) => setForm((current) => ({ ...current, coiffeuse_id: event.target.value }))}>
                      <option value="">Non assignee</option>
                      {lookups.coiffeuses.map((coiffeuse) => (
                        <option key={coiffeuse.id} value={coiffeuse.id}>{coiffeuse.prenom} {coiffeuse.nom}</option>
                      ))}
                    </select>
                  </FormField>
                  <FormField label="Date">
                    <input className={inputClass} type="date" value={form.date_reservation} onChange={(event) => setForm((current) => ({ ...current, date_reservation: event.target.value }))} required />
                  </FormField>
                  <FormField label="Heure debut">
                    <input className={inputClass} type="time" value={form.heure_debut} onChange={(event) => setForm((current) => ({ ...current, heure_debut: event.target.value }))} required />
                  </FormField>
                  <FormField label="Statut">
                    <select className={inputClass} value={form.statut} onChange={(event) => setForm((current) => ({ ...current, statut: event.target.value as ReservationStatus }))}>
                      {statusOptions.map((status) => (
                        <option key={status.value} value={status.value}>{status.label}</option>
                      ))}
                    </select>
                  </FormField>
                  <FormField label="Source">
                    <select className={inputClass} value={form.source} onChange={(event) => setForm((current) => ({ ...current, source: event.target.value as ReservationForm['source'] }))}>
                      <option value="admin">Admin</option>
                      <option value="telephone">Telephone</option>
                      <option value="whatsapp">WhatsApp</option>
                      <option value="physique">Physique</option>
                      <option value="en_ligne">En ligne</option>
                    </select>
                  </FormField>
                  <FormField label="Code promo">
                    <select className={inputClass} value={form.code_promo_id} onChange={(event) => setForm((current) => ({ ...current, code_promo_id: event.target.value }))}>
                      <option value="">Aucun</option>
                      {lookups.codesPromo.map((code) => (
                        <option key={code.id} value={code.id}>{code.code} - {code.nom ?? 'Code promo'}</option>
                      ))}
                    </select>
                  </FormField>
                  <FormField label="Fidelite">
                    <select className={inputClass} value={form.regle_fidelite_id} onChange={(event) => setForm((current) => ({ ...current, regle_fidelite_id: event.target.value }))}>
                      <option value="">Aucune</option>
                      {lookups.reglesFidelite.map((rule) => (
                        <option key={rule.id} value={rule.id}>{rule.nom}</option>
                      ))}
                    </select>
                  </FormField>
                  <FormField label="Acompte" hint="Vide = calcul automatique">
                    <input className={inputClass} type="number" min="0" step="100" value={form.montant_acompte} onChange={(event) => setForm((current) => ({ ...current, montant_acompte: event.target.value }))} />
                  </FormField>
                </div>
                <FormField label="Notes">
                  <textarea className={inputClass} rows={4} value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} placeholder="Notes internes..." />
                </FormField>
              </section>

              <section className="rounded-xl bg-[#fff8fb] p-4">
                <h3 className="text-base font-black text-[#111018]">Synthese</h3>
                <div className="mt-4 grid gap-3">
                  <div className="rounded-lg bg-white px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Prestations</p>
                    <p className="mt-1 text-xl font-black text-gray-950">{money(preview.subtotal)}</p>
                  </div>
                  <div className="rounded-lg bg-white px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Duree estimee</p>
                    <p className="mt-1 text-xl font-black text-gray-950">{minutesLabel(preview.duration)}</p>
                  </div>
                  <div className="rounded-lg bg-white px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Fin estimee</p>
                    <p className="mt-1 text-xl font-black text-gray-950">
                      {preview.duration > 0 ? estimatedEnd(form.date_reservation, form.heure_debut, preview.duration) : '-'}
                    </p>
                  </div>
                </div>
              </section>
            </div>

            <section className="rounded-xl border border-gray-100 p-4">
              <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h3 className="text-base font-black text-[#111018]">Prestations</h3>
                <button type="button" onClick={addDetail} className={`${secondaryButtonClass} inline-flex items-center justify-center gap-2`}>
                  <Plus className="h-4 w-4" />
                  Ajouter
                </button>
              </div>
              <div className="grid gap-3">
                {form.details.map((detail, index) => {
                  const coiffure = lookups.coiffures.find((item) => String(item.id) === detail.coiffure_id)
                  const variantes = activeVariantes(coiffure)
                  const options = coiffure?.options?.filter((option) => option.actif) ?? []

                  return (
                    <div key={index} className="rounded-xl border border-gray-100 bg-gray-50 p-3">
                      <div className="grid gap-3 lg:grid-cols-[1fr_1fr_110px_auto]">
                        <FormField label="Coiffure">
                          <select
                            className={inputClass}
                            value={detail.coiffure_id}
                            onChange={(event) => updateDetail(index, { ...detail, coiffure_id: event.target.value, variante_coiffure_id: '', option_ids: [] })}
                            required
                          >
                            <option value="">Selectionner</option>
                            {lookups.coiffures.map((coiffureItem) => (
                              <option key={coiffureItem.id} value={coiffureItem.id}>{coiffureItem.nom}</option>
                            ))}
                          </select>
                        </FormField>
                        <FormField label="Variante">
                          <select
                            className={inputClass}
                            value={detail.variante_coiffure_id}
                            onChange={(event) => updateDetail(index, { ...detail, variante_coiffure_id: event.target.value })}
                            required
                          >
                            <option value="">Selectionner</option>
                            {variantes.map((variante) => (
                              <option key={variante.id} value={variante.id}>{variante.nom} - {money(variante.prix)} - {minutesLabel(variante.duree_minutes)}</option>
                            ))}
                          </select>
                        </FormField>
                        <FormField label="Quantite">
                          <input className={inputClass} type="number" min="1" max="10" value={detail.quantite} onChange={(event) => updateDetail(index, { ...detail, quantite: event.target.value })} />
                        </FormField>
                        <button type="button" onClick={() => removeDetail(index)} disabled={form.details.length === 1} className={`${dangerButtonClass} mt-6 flex h-11 items-center justify-center px-3`} title="Retirer">
                          <X className="h-4 w-4" />
                        </button>
                      </div>
                      {options.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                          {options.map((option) => {
                            const checked = detail.option_ids.includes(option.id)

                            return (
                              <label key={option.id} className={`flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-xs font-black ${checked ? 'border-[#e91e63] bg-[#fff2f7] text-[#c41468]' : 'border-gray-200 bg-white text-gray-500'}`}>
                                <input
                                  type="checkbox"
                                  checked={checked}
                                  onChange={(event) => {
                                    const optionIds = event.target.checked
                                      ? [...detail.option_ids, option.id]
                                      : detail.option_ids.filter((id) => id !== option.id)
                                    updateDetail(index, { ...detail, option_ids: optionIds })
                                  }}
                                />
                                {option.nom} +{money(option.prix)}
                              </label>
                            )
                          })}
                        </div>
                      )}
                    </div>
                  )
                })}
              </div>
            </section>

            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
              <button type="button" onClick={resetForm} className={secondaryButtonClass}>Annuler</button>
              <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Enregistrement...' : editing ? 'Modifier' : 'Creer'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {detailModalOpen && detail && (
        <Modal title={`Reservation #${detail.id}`} onClose={() => setDetailModalOpen(false)} wide>
          {detailLoading ? (
            <div className="rounded-xl bg-gray-50 p-6 text-sm font-bold text-gray-500">Chargement de la reservation...</div>
          ) : (
            <div className="grid gap-5 xl:grid-cols-[1fr_1fr]">
              <section className="rounded-xl border border-gray-100 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="flex items-center gap-2 text-lg font-black text-gray-950">
                      <UserRound className="h-5 w-5 text-[#e91e63]" />
                      {clientName(detail)}
                    </p>
                    <p className="mt-1 text-sm font-semibold text-gray-500">{detail.client?.telephone ?? '-'}</p>
                  </div>
                  <span className={`rounded-full px-3 py-1 text-xs font-black ${statusClass(detail.statut)}`}>
                    {statusLabel(detail.statut)}
                  </span>
                </div>
                <div className="mt-4 grid gap-3 text-sm font-semibold text-gray-600">
                  <span className="inline-flex items-center gap-2"><CalendarDays className="h-4 w-4 text-[#e91e63]" />{formatDate(detail.date_reservation)}</span>
                  <span className="inline-flex items-center gap-2"><Clock3 className="h-4 w-4 text-gray-400" />{detail.heure_debut.slice(0, 5)} - {detail.heure_fin.slice(0, 5)}</span>
                  <span className="inline-flex items-center gap-2"><Scissors className="h-4 w-4 text-gray-400" />{coiffeuseName(detail)}</span>
                </div>
                <div className="mt-5 grid gap-3 sm:grid-cols-3">
                  <div className="rounded-lg bg-[#fff8fb] px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-[#c41468]">Total</p>
                    <p className="mt-1 text-lg font-black text-gray-950">{money(detail.montant_total, detail.devise)}</p>
                  </div>
                  <div className="rounded-lg bg-[#fff8fb] px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-[#c41468]">Acompte</p>
                    <p className="mt-1 text-lg font-black text-gray-950">{money(detail.montant_acompte, detail.devise)}</p>
                  </div>
                  <div className="rounded-lg bg-[#fff8fb] px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-[#c41468]">Reste</p>
                    <p className="mt-1 text-lg font-black text-gray-950">{money(detail.montant_restant, detail.devise)}</p>
                  </div>
                </div>
              </section>

              <section className="rounded-xl border border-gray-100 p-4">
                <h3 className="text-base font-black text-gray-950">Actions statut</h3>
                <div className="mt-4 grid gap-2 sm:grid-cols-2">
                  {statusOptions.map((status) => (
                    <button
                      key={status.value}
                      type="button"
                      onClick={() => void changeStatus(detail, status.value)}
                      className={`rounded-lg px-3 py-2 text-sm font-black transition ${detail.statut === status.value ? statusClass(status.value) : 'bg-gray-50 text-gray-600 hover:bg-gray-100'}`}
                    >
                      {status.label}
                    </button>
                  ))}
                </div>
              </section>

              <section className="rounded-xl border border-gray-100 p-4 xl:col-span-2">
                <h3 className="text-base font-black text-gray-950">Prestations</h3>
                <div className="mt-3 grid gap-3">
                  {detail.details?.map((item) => (
                    <div key={item.id} className="rounded-xl bg-gray-50 px-4 py-3">
                      <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                          <p className="font-black text-gray-950">{item.coiffure_nom}</p>
                          <p className="text-sm font-semibold text-gray-500">{item.variante_nom} x {item.quantite}</p>
                        </div>
                        <div className="text-left sm:text-right">
                          <p className="font-black text-[#c41468]">{money(item.montant_total, detail.devise)}</p>
                          <p className="text-xs font-bold text-gray-400">{minutesLabel(item.duree_minutes)}</p>
                        </div>
                      </div>
                      {item.options_snapshot && item.options_snapshot.length > 0 && (
                        <div className="mt-2 flex flex-wrap gap-2">
                          {item.options_snapshot.map((option) => (
                            <span key={option.id} className="rounded-full bg-white px-3 py-1 text-xs font-black text-gray-500">
                              {option.nom} +{money(option.prix, detail.devise)}
                            </span>
                          ))}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </section>

              <section className="rounded-xl border border-gray-100 p-4 xl:col-span-2">
                <h3 className="text-base font-black text-gray-950">Remises et notes</h3>
                <div className="mt-3 grid gap-3 sm:grid-cols-3">
                  <div className="rounded-lg bg-gray-50 px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Reduction</p>
                    <p className="mt-1 font-black text-gray-950">{money(detail.montant_reduction, detail.devise)}</p>
                  </div>
                  <div className="rounded-lg bg-gray-50 px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Promo</p>
                    <p className="mt-1 font-black text-gray-950">{detail.code_promo?.code ?? '-'}</p>
                  </div>
                  <div className="rounded-lg bg-gray-50 px-3 py-3">
                    <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">Fidelite</p>
                    <p className="mt-1 font-black text-gray-950">{detail.fidelite_appliquee ? detail.regle_fidelite?.nom ?? 'Appliquee' : '-'}</p>
                  </div>
                </div>
                <p className="mt-3 whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-sm font-semibold text-gray-600">
                  {detail.notes || 'Aucune note'}
                </p>
              </section>
            </div>
          )}
        </Modal>
      )}
    </AdminLayout>
  )
}

function estimatedEnd(date: string, time: string, minutes: number) {
  if (!date || !time || minutes <= 0) {
    return '-'
  }

  const start = new Date(`${date}T${time}:00`)

  if (Number.isNaN(start.getTime())) {
    return '-'
  }

  start.setMinutes(start.getMinutes() + minutes)

  return start.toTimeString().slice(0, 5)
}

export default ReservationsPage
