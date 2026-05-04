import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import {
  Ban,
  CheckCircle2,
  CircleDollarSign,
  Edit,
  Eye,
  FileText,
  Plus,
  Printer,
  ReceiptText,
  RefreshCw,
  Search,
  Send,
  Trash2,
  WalletCards,
} from 'lucide-react'
import AdminLayout from '../../../layouts/AdminLayout'
import {
  cancelPayment,
  createPayment,
  deletePayment,
  getPaymentLookups,
  getPayments,
  getReceipt,
  markReceiptSent,
  updatePayment,
} from './payments.api'
import {
  EmptyState,
  ErrorState,
  FormField,
  Modal,
  Pagination,
  SuccessState,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from './PaymentsUi'
import type {
  LaravelPaginated,
  Paiement,
  PaymentForm,
  PaymentLookups,
  PaymentMethod,
  PaymentReceipt,
  PaymentStatus,
  PaymentSummary,
  PaymentType,
  Reservation,
} from './payments.types'

const typeOptions: Array<{ value: PaymentType; label: string }> = [
  { value: 'acompte', label: 'Acompte' },
  { value: 'solde', label: 'Solde' },
  { value: 'complet', label: 'Paiement complet' },
  { value: 'ajustement', label: 'Ajustement' },
  { value: 'remboursement', label: 'Remboursement' },
]

const methodOptions: Array<{ value: PaymentMethod; label: string }> = [
  { value: 'especes', label: 'Especes' },
  { value: 'wave', label: 'Wave' },
  { value: 'orange_money', label: 'Orange Money' },
  { value: 'carte_bancaire', label: 'Carte bancaire' },
  { value: 'virement', label: 'Virement' },
  { value: 'autre', label: 'Autre' },
]

const statusOptions: Array<{ value: PaymentStatus; label: string }> = [
  { value: 'valide', label: 'Valide' },
  { value: 'en_attente', label: 'En attente' },
  { value: 'annule', label: 'Annule' },
  { value: 'rembourse', label: 'Rembourse' },
]

function nowInputValue() {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60000

  return new Date(now.getTime() - offset).toISOString().slice(0, 16)
}

const emptyForm = (): PaymentForm => ({
  reservation_id: '',
  client_id: '',
  type: 'acompte',
  mode_paiement: 'especes',
  montant: '',
  statut: 'valide',
  date_paiement: nowInputValue(),
  reference: '',
  notes: '',
  recu_envoye: false,
})

const emptySummary: PaymentSummary = {
  total_paye: 0,
  total_acomptes: 0,
  total_soldes: 0,
  total_attente: 0,
  total_annule: 0,
  nombre_paiements: 0,
}

function numberValue(value: number | string | null | undefined) {
  return Number(value ?? 0)
}

function money(value: number | string | null | undefined, devise = 'FCFA') {
  return `${numberValue(value).toLocaleString('fr-FR')} ${devise}`
}

function labelFor<T extends string>(items: Array<{ value: T; label: string }>, value: T) {
  return items.find((item) => item.value === value)?.label ?? value
}

function clientName(client?: { prenom?: string; nom?: string } | null) {
  return client ? `${client.prenom ?? ''} ${client.nom ?? ''}`.trim() : ''
}

function paymentClientName(payment: Paiement) {
  return clientName(payment.client) || clientName(payment.reservation?.client) || 'Client non renseigne'
}

function reservationLabel(reservation: Reservation) {
  const client = clientName(reservation.client)
  const date = formatDate(reservation.date_reservation)

  return `#${reservation.id} - ${client || 'Client supprime'} - ${date} - ${money(reservation.montant_restant, reservation.devise)} restant`
}

function servicesLabel(reservation?: Reservation | null) {
  const first = reservation?.details?.[0]

  if (!first) {
    return 'Reservation'
  }

  return [first.coiffure_nom, first.variante_nom].filter(Boolean).join(' - ')
}

function formatDate(value?: string | null) {
  if (!value) {
    return '-'
  }

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

function formatDateTime(value?: string | null) {
  if (!value) {
    return '-'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function toDateTimeInput(value?: string | null) {
  if (!value) {
    return nowInputValue()
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value.slice(0, 16).replace(' ', 'T')
  }

  const offset = date.getTimezoneOffset() * 60000

  return new Date(date.getTime() - offset).toISOString().slice(0, 16)
}

function statusClass(status: PaymentStatus) {
  if (status === 'valide') {
    return 'bg-emerald-50 text-emerald-700'
  }

  if (status === 'annule') {
    return 'bg-red-50 text-red-700'
  }

  if (status === 'rembourse') {
    return 'bg-sky-50 text-sky-700'
  }

  return 'bg-amber-50 text-amber-700'
}

function paymentToForm(payment: Paiement): PaymentForm {
  return {
    reservation_id: payment.reservation_id ? String(payment.reservation_id) : '',
    client_id: payment.client_id ? String(payment.client_id) : '',
    type: payment.type,
    mode_paiement: payment.mode_paiement,
    montant: String(payment.montant ?? ''),
    statut: payment.statut,
    date_paiement: toDateTimeInput(payment.date_paiement),
    reference: payment.reference ?? '',
    notes: payment.notes ?? '',
    recu_envoye: payment.recu_envoye,
  }
}

function suggestedAmount(reservation: Reservation, type: PaymentType) {
  if (type === 'acompte') {
    return Math.min(numberValue(reservation.montant_acompte), numberValue(reservation.montant_restant) || numberValue(reservation.montant_total))
  }

  if (type === 'remboursement') {
    return ''
  }

  return numberValue(reservation.montant_restant) || numberValue(reservation.montant_total)
}

function validateForm(form: PaymentForm, reservation: Reservation | null) {
  const amount = Number(form.montant)

  if (!form.reservation_id && !form.client_id) {
    return 'Selectionnez une reservation ou un client.'
  }

  if (Number.isNaN(amount) || amount <= 0) {
    return 'Le montant encaisse doit etre positif.'
  }

  if (!form.date_paiement) {
    return 'La date du paiement est obligatoire.'
  }

  if (reservation && form.type !== 'remboursement') {
    const remaining = numberValue(reservation.montant_restant) || numberValue(reservation.montant_total)

    if (amount > remaining) {
      return `Le montant depasse le reste a payer: ${money(remaining, reservation.devise)}.`
    }
  }

  return null
}

function PaymentsPage() {
  const [items, setItems] = useState<LaravelPaginated<Paiement> | null>(null)
  const [summary, setSummary] = useState<PaymentSummary>(emptySummary)
  const [lookups, setLookups] = useState<PaymentLookups>({ reservations: [], clients: [] })
  const [form, setForm] = useState<PaymentForm>(emptyForm)
  const [editingPayment, setEditingPayment] = useState<Paiement | null>(null)
  const [receipt, setReceipt] = useState<PaymentReceipt | null>(null)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [typeFilter, setTypeFilter] = useState('all')
  const [methodFilter, setMethodFilter] = useState('all')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [loading, setLoading] = useState(true)
  const [lookupsLoading, setLookupsLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [receiptLoading, setReceiptLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [receiptModalOpen, setReceiptModalOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const filtersReady = useRef(false)

  const payments = useMemo(() => items?.data ?? [], [items])
  const selectedReservation = useMemo(
    () => lookups.reservations.find((reservation) => String(reservation.id) === form.reservation_id) ?? null,
    [form.reservation_id, lookups.reservations],
  )

  const loadPage = useCallback(
    async (
      nextPage: number,
      nextSearch: string,
      nextStatus: string,
      nextType: string,
      nextMethod: string,
      nextDateFrom: string,
      nextDateTo: string,
    ) => {
      setLoading(true)
      setError(null)

      try {
        const response = await getPayments({
          page: nextPage,
          per_page: 12,
          search: nextSearch || undefined,
          statut: nextStatus === 'all' ? undefined : nextStatus,
          type: nextType === 'all' ? undefined : nextType,
          mode_paiement: nextMethod === 'all' ? undefined : nextMethod,
          date_from: nextDateFrom || undefined,
          date_to: nextDateTo || undefined,
        })

        setItems(response.data)
        setSummary(response.meta ?? emptySummary)
        setPage(nextPage)
      } catch {
        setError('Impossible de charger les paiements.')
      } finally {
        setLoading(false)
      }
    },
    [],
  )

  useEffect(() => {
    let cancelled = false

    Promise.all([
      getPayments({ page: 1, per_page: 12 }),
      getPaymentLookups(),
    ])
      .then(([paymentsResponse, lookupResponse]) => {
        if (!cancelled) {
          setItems(paymentsResponse.data)
          setSummary(paymentsResponse.meta ?? emptySummary)
          setLookups(lookupResponse)
          setPage(1)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError('Impossible de charger le module paiements.')
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
          setLookupsLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (!filtersReady.current) {
      filtersReady.current = true
      return
    }

    const timeoutId = window.setTimeout(() => {
      void loadPage(1, search, statusFilter, typeFilter, methodFilter, dateFrom, dateTo)
    }, 300)

    return () => window.clearTimeout(timeoutId)
  }, [dateFrom, dateTo, loadPage, methodFilter, search, statusFilter, typeFilter])

  const resetForm = () => {
    setForm(emptyForm())
    setEditingPayment(null)
    setModalOpen(false)
  }

  const openPaymentModal = (payment?: Paiement) => {
    setError(null)
    setSuccess(null)
    if (payment) {
      setEditingPayment(payment)
      setForm(paymentToForm(payment))
    } else {
      setEditingPayment(null)
      setForm(emptyForm())
    }
    setModalOpen(true)
  }

  const updateReservation = (reservationId: string) => {
    const reservation = lookups.reservations.find((item) => String(item.id) === reservationId)

    setForm((current) => ({
      ...current,
      reservation_id: reservationId,
      client_id: reservation?.client_id ? String(reservation.client_id) : current.client_id,
      montant: reservation ? String(suggestedAmount(reservation, current.type) || current.montant) : current.montant,
    }))
  }

  const updateType = (type: PaymentType) => {
    setForm((current) => {
      const reservation = lookups.reservations.find((item) => String(item.id) === current.reservation_id)

      return {
        ...current,
        type,
        montant: reservation && !editingPayment ? String(suggestedAmount(reservation, type) || current.montant) : current.montant,
      }
    })
  }

  const submitPayment = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    const validationError = validateForm(form, selectedReservation)
    if (validationError) {
      setError(validationError)
      return
    }

    setSaving(true)
    try {
      const response = editingPayment
        ? await updatePayment(editingPayment.id, form)
        : await createPayment(form)

      resetForm()
      setSuccess(editingPayment ? 'Paiement mis a jour.' : 'Paiement enregistre et recu genere.')
      if (response.receipt) {
        setReceipt(response.receipt)
        setReceiptModalOpen(true)
      }
      await loadPage(page, search, statusFilter, typeFilter, methodFilter, dateFrom, dateTo)
      setLookups(await getPaymentLookups())
    } catch {
      setError('Enregistrement impossible. Verifiez le montant, la reservation et la caisse.')
    } finally {
      setSaving(false)
    }
  }

  const openReceipt = async (payment: Paiement) => {
    setReceiptModalOpen(true)
    setReceipt(null)
    setReceiptLoading(true)
    setError(null)

    try {
      setReceipt(await getReceipt(payment.id))
    } catch {
      setError('Impossible de charger le recu.')
    } finally {
      setReceiptLoading(false)
    }
  }

  const markSent = async () => {
    if (!receipt) {
      return
    }

    setSaving(true)
    try {
      await markReceiptSent(receipt.paiement.id)
      setReceipt(await getReceipt(receipt.paiement.id))
      setSuccess('Recu marque comme envoye.')
      await loadPage(page, search, statusFilter, typeFilter, methodFilter, dateFrom, dateTo)
    } catch {
      setError('Impossible de marquer le recu comme envoye.')
    } finally {
      setSaving(false)
    }
  }

  const cancel = async (payment: Paiement) => {
    if (!window.confirm(`Annuler le paiement ${payment.numero_recu} ?`)) {
      return
    }

    try {
      await cancelPayment(payment.id, 'Paiement annule depuis l administration.')
      setSuccess('Paiement annule.')
      await loadPage(page, search, statusFilter, typeFilter, methodFilter, dateFrom, dateTo)
      setLookups(await getPaymentLookups())
    } catch {
      setError('Annulation impossible. La caisse associee est peut-etre fermee.')
    }
  }

  const remove = async (payment: Paiement) => {
    if (!window.confirm(`Supprimer le paiement ${payment.numero_recu} ?`)) {
      return
    }

    try {
      await deletePayment(payment.id)
      setSuccess('Paiement supprime.')
      await loadPage(page, search, statusFilter, typeFilter, methodFilter, dateFrom, dateTo)
      setLookups(await getPaymentLookups())
    } catch {
      setError('Suppression impossible. La caisse associee est peut-etre fermee.')
    }
  }

  return (
    <AdminLayout>
      <div className="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#e91e63]">
            Module paiements
          </p>
          <h1 className="mt-2 text-2xl font-black text-[#111018] sm:text-3xl">Paiements & Recus</h1>
          <p className="mt-2 max-w-3xl text-sm font-medium text-gray-500">
            Encaissements, acomptes, soldes, mouvements de caisse et recus clients.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <button
            type="button"
            onClick={() => void loadPage(page, search, statusFilter, typeFilter, methodFilter, dateFrom, dateTo)}
            className={`${secondaryButtonClass} inline-flex items-center justify-center gap-2`}
          >
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            Actualiser
          </button>
          <button
            type="button"
            onClick={() => openPaymentModal()}
            className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}
          >
            <Plus className="h-4 w-4" />
            Nouveau paiement
          </button>
        </div>
      </div>

      <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.08em] text-gray-400">
            <CircleDollarSign className="h-4 w-4 text-[#e91e63]" />
            Encaisse
          </p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{money(summary.total_paye)}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">{summary.nombre_paiements} paiement(s) filtre(s)</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.08em] text-gray-400">
            <WalletCards className="h-4 w-4 text-[#c41468]" />
            Acomptes
          </p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{money(summary.total_acomptes)}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Reservations securisees</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.08em] text-gray-400">
            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
            Soldes
          </p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{money(summary.total_soldes)}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Soldes et paiements complets</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.08em] text-gray-400">
            <ReceiptText className="h-4 w-4 text-amber-600" />
            En attente
          </p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{summary.total_attente}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">A valider</p>
        </div>
      </section>

      <section className="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="grid gap-3 xl:grid-cols-[minmax(220px,1fr)_repeat(5,auto)] xl:items-center">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
              placeholder="Recu, client, reference, coiffure..."
            />
          </div>
          <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className={inputClass}>
            <option value="all">Tous statuts</option>
            {statusOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <select value={typeFilter} onChange={(event) => setTypeFilter(event.target.value)} className={inputClass}>
            <option value="all">Tous types</option>
            {typeOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <select value={methodFilter} onChange={(event) => setMethodFilter(event.target.value)} className={inputClass}>
            <option value="all">Tous modes</option>
            {methodOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} className={inputClass} />
          <input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} className={inputClass} />
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
        ) : payments.length === 0 ? (
          <EmptyState label="Aucun paiement trouve." />
        ) : (
          payments.map((payment) => (
            <article key={payment.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.55)]">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate text-base font-black text-gray-950">{payment.numero_recu}</p>
                  <p className="mt-1 truncate text-sm font-semibold text-gray-500">{paymentClientName(payment)}</p>
                  <p className="mt-1 truncate text-sm font-semibold text-[#c41468]">{servicesLabel(payment.reservation)}</p>
                </div>
                <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-black ${statusClass(payment.statut)}`}>
                  {labelFor(statusOptions, payment.statut)}
                </span>
              </div>
              <div className="mt-4 grid gap-2 text-sm font-bold text-gray-500">
                <span>{formatDateTime(payment.date_paiement)}</span>
                <span>{labelFor(typeOptions, payment.type)} - {labelFor(methodOptions, payment.mode_paiement)}</span>
                <span className="text-lg font-black text-gray-950">{money(payment.montant, payment.devise)}</span>
              </div>
              <div className="mt-4 flex flex-wrap justify-end gap-1">
                <button type="button" onClick={() => void openReceipt(payment)} className="flex h-9 w-9 items-center justify-center rounded-lg text-[#c41468] transition hover:bg-[#fff2f7]" title="Recu">
                  <FileText className="h-4 w-4" />
                </button>
                <button type="button" onClick={() => openPaymentModal(payment)} className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                  <Edit className="h-4 w-4" />
                </button>
                {payment.statut !== 'annule' && (
                  <button type="button" onClick={() => void cancel(payment)} className="flex h-9 w-9 items-center justify-center rounded-lg text-amber-700 transition hover:bg-amber-50" title="Annuler">
                    <Ban className="h-4 w-4" />
                  </button>
                )}
                <button type="button" onClick={() => void remove(payment)} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Supprimer">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </article>
          ))
        )}
      </section>

      <section className="hidden overflow-hidden rounded-xl border border-gray-100 bg-white shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)] lg:block">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[980px] text-left text-sm">
            <thead className="bg-[#fbf8fa] text-xs uppercase tracking-[0.08em] text-gray-500">
              <tr>
                <th className="px-5 py-3">Recu</th>
                <th className="px-5 py-3">Client</th>
                <th className="px-5 py-3">Reservation</th>
                <th className="px-5 py-3">Paiement</th>
                <th className="px-5 py-3">Montant</th>
                <th className="px-5 py-3">Statut</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                Array.from({ length: 5 }).map((_, index) => (
                  <tr key={index} className="border-t border-gray-100">
                    <td colSpan={7} className="px-5 py-4">
                      <div className="h-5 w-full animate-pulse rounded bg-gray-100" />
                    </td>
                  </tr>
                ))
              ) : payments.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-6">
                    <EmptyState label="Aucun paiement trouve." />
                  </td>
                </tr>
              ) : (
                payments.map((payment) => (
                  <tr key={payment.id} className="border-t border-gray-100 align-top">
                    <td className="px-5 py-4">
                      <div className="font-black text-gray-950">{payment.numero_recu}</div>
                      <div className="mt-1 text-xs font-bold text-gray-400">{formatDateTime(payment.date_paiement)}</div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="font-black text-gray-950">{paymentClientName(payment)}</div>
                      <div className="mt-1 text-xs font-bold text-gray-400">{payment.client?.telephone ?? payment.reservation?.client?.telephone ?? 'Telephone non renseigne'}</div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="font-bold text-gray-900">{payment.reservation_id ? `Reservation #${payment.reservation_id}` : 'Hors reservation'}</div>
                      <div className="mt-1 text-xs font-bold text-gray-500">{servicesLabel(payment.reservation)}</div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="font-bold text-gray-900">{labelFor(typeOptions, payment.type)}</div>
                      <div className="mt-1 text-xs font-bold text-gray-500">{labelFor(methodOptions, payment.mode_paiement)}</div>
                    </td>
                    <td className="px-5 py-4 font-black text-[#c41468]">{money(payment.montant, payment.devise)}</td>
                    <td className="px-5 py-4">
                      <span className={`rounded-full px-3 py-1 text-xs font-black ${statusClass(payment.statut)}`}>
                        {labelFor(statusOptions, payment.statut)}
                      </span>
                      <div className="mt-2 text-xs font-bold text-gray-400">
                        {payment.recu_envoye ? 'Recu envoye' : 'Recu non envoye'}
                      </div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex justify-end gap-1">
                        <button type="button" onClick={() => void openReceipt(payment)} className="flex h-9 w-9 items-center justify-center rounded-lg text-[#c41468] transition hover:bg-[#fff2f7]" title="Voir recu">
                          <Eye className="h-4 w-4" />
                        </button>
                        <button type="button" onClick={() => openPaymentModal(payment)} className="flex h-9 w-9 items-center justify-center rounded-lg text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                          <Edit className="h-4 w-4" />
                        </button>
                        {payment.statut !== 'annule' && (
                          <button type="button" onClick={() => void cancel(payment)} className="flex h-9 w-9 items-center justify-center rounded-lg text-amber-700 transition hover:bg-amber-50" title="Annuler">
                            <Ban className="h-4 w-4" />
                          </button>
                        )}
                        <button type="button" onClick={() => void remove(payment)} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Supprimer">
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
          onPrevious={() => void loadPage(page - 1, search, statusFilter, typeFilter, methodFilter, dateFrom, dateTo)}
          onNext={() => void loadPage(page + 1, search, statusFilter, typeFilter, methodFilter, dateFrom, dateTo)}
        />
      )}

      {modalOpen && (
        <Modal title={editingPayment ? 'Modifier paiement' : 'Nouveau paiement'} onClose={resetForm} wide>
          <form onSubmit={submitPayment} className="space-y-5">
            <div className="grid gap-4 lg:grid-cols-3">
              <FormField label="Reservation" hint={lookupsLoading ? 'Chargement...' : 'Optionnel si paiement hors reservation'}>
                <select className={inputClass} value={form.reservation_id} onChange={(event) => updateReservation(event.target.value)}>
                  <option value="">Paiement hors reservation</option>
                  {lookups.reservations.map((reservation) => (
                    <option key={reservation.id} value={reservation.id}>
                      {reservationLabel(reservation)}
                    </option>
                  ))}
                </select>
              </FormField>
              <FormField label="Client">
                <select className={inputClass} value={form.client_id} onChange={(event) => setForm((current) => ({ ...current, client_id: event.target.value }))} disabled={form.reservation_id !== ''}>
                  <option value="">Selectionner client</option>
                  {lookups.clients.map((client) => (
                    <option key={client.id} value={client.id}>
                      {clientName(client)} - {client.telephone}
                    </option>
                  ))}
                </select>
              </FormField>
              <FormField label="Type">
                <select className={inputClass} value={form.type} onChange={(event) => updateType(event.target.value as PaymentType)}>
                  {typeOptions.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                  ))}
                </select>
              </FormField>
              <FormField label="Mode paiement">
                <select className={inputClass} value={form.mode_paiement} onChange={(event) => setForm((current) => ({ ...current, mode_paiement: event.target.value as PaymentMethod }))}>
                  {methodOptions.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                  ))}
                </select>
              </FormField>
              <FormField label="Montant">
                <input className={inputClass} type="number" min="1" step="100" value={form.montant} onChange={(event) => setForm((current) => ({ ...current, montant: event.target.value }))} placeholder="25000" />
              </FormField>
              <FormField label="Statut">
                <select className={inputClass} value={form.statut} onChange={(event) => setForm((current) => ({ ...current, statut: event.target.value as PaymentStatus }))}>
                  {statusOptions.map((option) => (
                    <option key={option.value} value={option.value}>{option.label}</option>
                  ))}
                </select>
              </FormField>
              <FormField label="Date paiement">
                <input className={inputClass} type="datetime-local" value={form.date_paiement} onChange={(event) => setForm((current) => ({ ...current, date_paiement: event.target.value }))} />
              </FormField>
              <FormField label="Reference">
                <input className={inputClass} value={form.reference} onChange={(event) => setForm((current) => ({ ...current, reference: event.target.value }))} placeholder="Transaction Wave, ticket, note..." />
              </FormField>
              <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold lg:self-end">
                <input type="checkbox" checked={form.recu_envoye} onChange={(event) => setForm((current) => ({ ...current, recu_envoye: event.target.checked }))} />
                Recu deja envoye
              </label>
              <FormField label="Notes">
                <textarea className={inputClass} rows={4} value={form.notes} onChange={(event) => setForm((current) => ({ ...current, notes: event.target.value }))} placeholder="Commentaire interne..." />
              </FormField>
              <div className="rounded-xl border border-[#f1e7ee] bg-[#fff8fb] p-4 lg:col-span-2">
                <p className="text-sm font-black text-[#c41468]">Resume reservation</p>
                {selectedReservation ? (
                  <div className="mt-3 grid gap-3 text-sm font-bold text-gray-600 sm:grid-cols-3">
                    <span>Total : {money(selectedReservation.montant_total, selectedReservation.devise)}</span>
                    <span>Acompte prevu : {money(selectedReservation.montant_acompte, selectedReservation.devise)}</span>
                    <span>Reste : {money(selectedReservation.montant_restant, selectedReservation.devise)}</span>
                  </div>
                ) : (
                  <p className="mt-3 text-sm font-bold text-gray-500">Aucune reservation selectionnee.</p>
                )}
              </div>
            </div>
            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
              <button type="button" onClick={resetForm} className={secondaryButtonClass}>Annuler</button>
              <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Enregistrement...' : editingPayment ? 'Modifier' : 'Encaisser'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {receiptModalOpen && (
        <Modal title="Recu paiement" onClose={() => setReceiptModalOpen(false)} wide>
          {receiptLoading || !receipt ? (
            <div className="rounded-xl bg-gray-50 p-6 text-sm font-bold text-gray-500">Chargement du recu...</div>
          ) : (
            <div className="grid gap-5 xl:grid-cols-[1fr_330px]">
              <section className="rounded-xl border border-gray-100 bg-white p-5">
                <div className="flex flex-col gap-4 border-b border-gray-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <p className="font-display text-3xl leading-7 text-[#e91e63]">{receipt.salon.nom}</p>
                    <p className="mt-1 text-sm font-bold text-gray-500">{receipt.salon.description}</p>
                    {receipt.salon.telephone_whatsapp && (
                      <p className="mt-1 text-sm font-semibold text-gray-500">{receipt.salon.telephone_whatsapp}</p>
                    )}
                  </div>
                  <div className="rounded-xl bg-[#fff2f7] px-4 py-3 text-left sm:text-right">
                    <p className="text-xs font-black uppercase tracking-[0.12em] text-[#c41468]">Recu</p>
                    <p className="mt-1 text-lg font-black text-gray-950">{receipt.numero_recu}</p>
                    <p className="mt-1 text-xs font-bold text-gray-500">{formatDateTime(receipt.date)}</p>
                  </div>
                </div>

                <div className="grid gap-4 border-b border-gray-100 py-5 md:grid-cols-2">
                  <div>
                    <p className="text-xs font-black uppercase tracking-[0.12em] text-gray-400">Client</p>
                    <p className="mt-2 text-lg font-black text-gray-950">{receipt.client.nom}</p>
                    <p className="mt-1 text-sm font-semibold text-gray-500">{receipt.client.telephone ?? 'Telephone non renseigne'}</p>
                  </div>
                  <div>
                    <p className="text-xs font-black uppercase tracking-[0.12em] text-gray-400">Reservation</p>
                    <p className="mt-2 text-lg font-black text-gray-950">
                      {receipt.reservation ? `Reservation #${receipt.reservation.id}` : 'Hors reservation'}
                    </p>
                    {receipt.reservation && (
                      <p className="mt-1 text-sm font-semibold text-gray-500">
                        {formatDate(receipt.reservation.date_reservation)} a {receipt.reservation.heure_debut}
                      </p>
                    )}
                  </div>
                </div>

                <div className="py-5">
                  <p className="text-xs font-black uppercase tracking-[0.12em] text-gray-400">Prestations</p>
                  <div className="mt-3 overflow-hidden rounded-xl border border-gray-100">
                    <table className="w-full text-left text-sm">
                      <thead className="bg-[#fbf8fa] text-xs uppercase text-gray-500">
                        <tr>
                          <th className="px-4 py-3">Service</th>
                          <th className="px-4 py-3">Quantite</th>
                          <th className="px-4 py-3 text-right">Montant</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(receipt.reservation?.services ?? []).length === 0 ? (
                          <tr>
                            <td colSpan={3} className="px-4 py-4 text-sm font-bold text-gray-500">Paiement hors reservation.</td>
                          </tr>
                        ) : (
                          receipt.reservation?.services.map((service, index) => (
                            <tr key={`${service.coiffure}-${index}`} className="border-t border-gray-100">
                              <td className="px-4 py-3 font-bold text-gray-900">
                                {[service.coiffure, service.variante].filter(Boolean).join(' - ')}
                              </td>
                              <td className="px-4 py-3 text-gray-600">{service.quantite}</td>
                              <td className="px-4 py-3 text-right font-bold text-gray-900">{money(service.montant, receipt.paiement.devise)}</td>
                            </tr>
                          ))
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="grid gap-3 rounded-xl bg-[#fbf8fa] p-4 text-sm font-bold text-gray-600 sm:grid-cols-3">
                  <span>Total : {money(receipt.totaux.montant_reservation, receipt.paiement.devise)}</span>
                  <span>Paye : {money(receipt.totaux.montant_deja_paye, receipt.paiement.devise)}</span>
                  <span>Reste : {money(receipt.totaux.reste_a_payer, receipt.paiement.devise)}</span>
                </div>
              </section>

              <aside className="space-y-3">
                <div className="rounded-xl border border-gray-100 bg-white p-4">
                  <p className="text-xs font-black uppercase tracking-[0.12em] text-gray-400">Paiement</p>
                  <p className="mt-2 text-3xl font-black text-[#c41468]">{money(receipt.paiement.montant, receipt.paiement.devise)}</p>
                  <div className="mt-4 grid gap-2 text-sm font-bold text-gray-600">
                    <span>{labelFor(typeOptions, receipt.paiement.type)}</span>
                    <span>{labelFor(methodOptions, receipt.paiement.mode_paiement)}</span>
                    <span className={`w-max rounded-full px-3 py-1 text-xs font-black ${statusClass(receipt.paiement.statut)}`}>
                      {labelFor(statusOptions, receipt.paiement.statut)}
                    </span>
                    <span>{receipt.paiement.reference ?? 'Reference non renseignee'}</span>
                  </div>
                </div>
                <button type="button" onClick={() => window.print()} className={`${primaryButtonClass} inline-flex w-full items-center justify-center gap-2`}>
                  <Printer className="h-4 w-4" />
                  Imprimer
                </button>
                <button type="button" onClick={() => void markSent()} disabled={saving || receipt.paiement.recu_envoye} className={`${secondaryButtonClass} inline-flex w-full items-center justify-center gap-2`}>
                  <Send className="h-4 w-4" />
                  {receipt.paiement.recu_envoye ? 'Recu envoye' : 'Marquer envoye'}
                </button>
                <button type="button" onClick={() => setReceiptModalOpen(false)} className={`${secondaryButtonClass} w-full`}>
                  Fermer
                </button>
              </aside>
            </div>
          )}
        </Modal>
      )}
    </AdminLayout>
  )
}

export default PaymentsPage
