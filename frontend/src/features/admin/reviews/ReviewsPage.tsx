import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import {
  CheckCircle2,
  EyeOff,
  MessageCircle,
  RefreshCw,
  Search,
  Star,
  Trash2,
} from 'lucide-react'
import AdminLayout from '../../../layouts/AdminLayout'
import {
  approveCoiffureReview,
  deleteCoiffureReview,
  getCoiffureReviews,
  rejectCoiffureReview,
} from './reviews.api'
import type { CoiffureReview, LaravelPaginated, ReviewStatus, ReviewSummary } from './reviews.types'
import {
  EmptyState,
  ErrorState,
  Pagination,
  SuccessState,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from '../payments/PaymentsUi'

const emptySummary: ReviewSummary = {
  total: 0,
  en_attente: 0,
  approuves: 0,
  rejetes: 0,
}

const statusOptions: Array<{ value: ReviewStatus | 'all'; label: string }> = [
  { value: 'all', label: 'Tous les avis' },
  { value: 'en_attente', label: 'En attente' },
  { value: 'approuve', label: 'Approuves' },
  { value: 'rejete', label: 'Rejetes' },
]

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
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function statusLabel(status: ReviewStatus) {
  return statusOptions.find((option) => option.value === status)?.label ?? status
}

function statusClass(status: ReviewStatus) {
  if (status === 'approuve') {
    return 'bg-emerald-50 text-emerald-700'
  }

  if (status === 'rejete') {
    return 'bg-red-50 text-red-700'
  }

  return 'bg-amber-50 text-amber-700'
}

function RatingStars({ value }: { value: number }) {
  return (
    <span className="inline-flex items-center gap-0.5 text-[#f59e0b]">
      {Array.from({ length: 5 }, (_, index) => (
        <Star key={index} className={`h-4 w-4 ${index < value ? 'fill-[#f59e0b]' : 'fill-none text-gray-300'}`} />
      ))}
    </span>
  )
}

function clientName(review: CoiffureReview) {
  if (review.client) {
    return `${review.client.prenom} ${review.client.nom}`.trim()
  }

  return review.nom_client
}

function ReviewsPage() {
  const [items, setItems] = useState<LaravelPaginated<CoiffureReview> | null>(null)
  const [summary, setSummary] = useState<ReviewSummary>(emptySummary)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<ReviewStatus | 'all'>('en_attente')
  const [loading, setLoading] = useState(true)
  const [savingId, setSavingId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const filtersReady = useRef(false)

  const reviews = useMemo(() => items?.data ?? [], [items])

  const loadPage = useCallback(async (nextPage: number, nextSearch: string, nextStatus: ReviewStatus | 'all') => {
    setLoading(true)
    setError(null)

    try {
      const response = await getCoiffureReviews({
        page: nextPage,
        per_page: 12,
        search: nextSearch || undefined,
        statut: nextStatus,
      })

      setItems(response.data)
      setSummary(response.meta ?? emptySummary)
      setPage(nextPage)
    } catch {
      setError('Impossible de charger les avis clientes.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadPage(1, search, statusFilter)
  }, [])

  useEffect(() => {
    if (!filtersReady.current) {
      filtersReady.current = true
      return
    }

    const timeoutId = window.setTimeout(() => {
      void loadPage(1, search, statusFilter)
    }, 300)

    return () => window.clearTimeout(timeoutId)
  }, [loadPage, search, statusFilter])

  const approve = async (review: CoiffureReview) => {
    setSavingId(review.id)
    setError(null)
    setSuccess(null)

    try {
      await approveCoiffureReview(review.id)
      setSuccess('Avis approuve et publie sur la page client.')
      await loadPage(page, search, statusFilter)
    } catch {
      setError('Impossible d approuver cet avis.')
    } finally {
      setSavingId(null)
    }
  }

  const reject = async (review: CoiffureReview) => {
    setSavingId(review.id)
    setError(null)
    setSuccess(null)

    try {
      await rejectCoiffureReview(review.id)
      setSuccess('Avis rejete.')
      await loadPage(page, search, statusFilter)
    } catch {
      setError('Impossible de rejeter cet avis.')
    } finally {
      setSavingId(null)
    }
  }

  const remove = async (review: CoiffureReview) => {
    if (!window.confirm(`Supprimer l avis de ${clientName(review)} ?`)) {
      return
    }

    setSavingId(review.id)
    setError(null)
    setSuccess(null)

    try {
      await deleteCoiffureReview(review.id)
      setSuccess('Avis supprime.')
      await loadPage(page, search, statusFilter)
    } catch {
      setError('Impossible de supprimer cet avis.')
    } finally {
      setSavingId(null)
    }
  }

  return (
    <AdminLayout>
      <div className="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p className="text-xs font-bold uppercase text-[#e91e63]">Validation sociale</p>
          <h1 className="mt-2 text-2xl font-black text-[#111018] sm:text-3xl">Avis clientes</h1>
          <p className="mt-2 max-w-3xl text-sm font-medium text-gray-500">
            Validez les commentaires affiches sur les coiffures et gardez une preuve client fiable.
          </p>
        </div>
        <button type="button" onClick={() => void loadPage(page, search, statusFilter)} className={`${secondaryButtonClass} inline-flex items-center justify-center gap-2`}>
          <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          Actualiser
        </button>
      </div>

      <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase text-gray-400">Total avis</p>
          <p className="mt-1 text-2xl font-black text-[#111018]">{summary.total}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Tous statuts</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase text-gray-400">A valider</p>
          <p className="mt-1 text-2xl font-black text-amber-700">{summary.en_attente}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">En attente admin</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase text-gray-400">Publies</p>
          <p className="mt-1 text-2xl font-black text-emerald-700">{summary.approuves}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Visibles cote client</p>
        </div>
        <div className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
          <p className="text-xs font-black uppercase text-gray-400">Rejetes</p>
          <p className="mt-1 text-2xl font-black text-red-700">{summary.rejetes}</p>
          <p className="mt-1 text-xs font-bold text-gray-500">Masques</p>
        </div>
      </section>

      {error && <div className="mb-4"><ErrorState label={error} /></div>}
      {success && <div className="mb-4"><SuccessState label={success} /></div>}

      <section className="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="grid gap-3 lg:grid-cols-[1fr_220px] lg:items-center">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
              placeholder="Cliente, telephone, coiffure, commentaire..."
            />
          </div>
          <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value as ReviewStatus | 'all')} className={inputClass}>
            {statusOptions.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
        </div>
      </section>

      <section className="grid gap-3 lg:hidden">
        {loading ? (
          Array.from({ length: 4 }).map((_, index) => (
            <article key={index} className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
              <div className="h-5 w-2/3 animate-pulse rounded bg-gray-100" />
              <div className="mt-3 h-4 w-full animate-pulse rounded bg-gray-100" />
              <div className="mt-3 h-4 w-1/2 animate-pulse rounded bg-gray-100" />
            </article>
          ))
        ) : reviews.length === 0 ? (
          <EmptyState label="Aucun avis trouve." />
        ) : (
          reviews.map((review) => (
            <article key={review.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.55)]">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate text-base font-black text-gray-950">{clientName(review)}</p>
                  <p className="mt-1 text-xs font-bold text-gray-400">{review.coiffure?.nom ?? 'Coiffure'} - {formatDate(review.created_at)}</p>
                  <div className="mt-2 flex items-center gap-2">
                    <RatingStars value={review.note} />
                    {review.verifie ? <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700">Verifie</span> : null}
                  </div>
                </div>
                <span className={`shrink-0 rounded-full px-3 py-1 text-xs font-black ${statusClass(review.statut)}`}>{statusLabel(review.statut)}</span>
              </div>
              <p className="mt-3 text-sm font-semibold leading-6 text-gray-600">{review.commentaire}</p>
              <div className="mt-4 flex flex-wrap justify-end gap-2">
                {review.statut !== 'approuve' && (
                  <button type="button" onClick={() => void approve(review)} disabled={savingId === review.id} className={`${primaryButtonClass} inline-flex items-center gap-2`}>
                    <CheckCircle2 className="h-4 w-4" />
                    Approuver
                  </button>
                )}
                {review.statut !== 'rejete' && (
                  <button type="button" onClick={() => void reject(review)} disabled={savingId === review.id} className={`${secondaryButtonClass} inline-flex items-center gap-2`}>
                    <EyeOff className="h-4 w-4" />
                    Rejeter
                  </button>
                )}
                <button type="button" onClick={() => void remove(review)} disabled={savingId === review.id} className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm font-black text-red-700">
                  Supprimer
                </button>
              </div>
            </article>
          ))
        )}
      </section>

      <section className="hidden overflow-hidden rounded-xl border border-gray-100 bg-white shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)] lg:block">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[1040px] text-left text-sm">
            <thead className="bg-gray-50 text-xs font-black uppercase text-gray-500">
              <tr>
                <th className="px-5 py-3">Cliente</th>
                <th className="px-5 py-3">Coiffure</th>
                <th className="px-5 py-3">Avis</th>
                <th className="px-5 py-3">Statut</th>
                <th className="px-5 py-3">Date</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                Array.from({ length: 5 }).map((_, row) => (
                  <tr key={row}>
                    {Array.from({ length: 6 }).map((__, cell) => (
                      <td key={cell} className="px-5 py-4">
                        <div className="h-5 animate-pulse rounded bg-gray-100" />
                      </td>
                    ))}
                  </tr>
                ))
              ) : reviews.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-8">
                    <EmptyState label="Aucun avis trouve." />
                  </td>
                </tr>
              ) : (
                reviews.map((review) => (
                  <tr key={review.id} className="align-top transition hover:bg-[#fff8fb]">
                    <td className="px-5 py-4">
                      <div className="font-black text-gray-950">{clientName(review)}</div>
                      <div className="mt-1 text-xs font-bold text-gray-400">{review.telephone ?? review.email ?? 'Contact non renseigne'}</div>
                      {review.verifie ? <span className="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700">Cliente verifiee</span> : null}
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-3">
                        <div className="grid h-10 w-10 place-items-center overflow-hidden rounded-xl bg-[#fff2f7] text-[#c41468]">
                          {review.coiffure?.image ? (
                            <img src={review.coiffure.image} alt="" className="h-full w-full object-cover" />
                          ) : (
                            <MessageCircle className="h-4 w-4" />
                          )}
                        </div>
                        <span className="font-bold text-gray-900">{review.coiffure?.nom ?? 'Coiffure supprimee'}</span>
                      </div>
                    </td>
                    <td className="max-w-xl px-5 py-4">
                      <RatingStars value={review.note} />
                      <p className="mt-2 line-clamp-3 font-semibold leading-6 text-gray-600">{review.commentaire}</p>
                    </td>
                    <td className="px-5 py-4">
                      <span className={`rounded-full px-3 py-1 text-xs font-black ${statusClass(review.statut)}`}>{statusLabel(review.statut)}</span>
                    </td>
                    <td className="px-5 py-4 font-semibold text-gray-500">{formatDate(review.created_at)}</td>
                    <td className="px-5 py-4">
                      <div className="flex justify-end gap-1">
                        {review.statut !== 'approuve' && (
                          <button type="button" onClick={() => void approve(review)} disabled={savingId === review.id} className="flex h-9 w-9 items-center justify-center rounded-lg text-emerald-700 transition hover:bg-emerald-50" title="Approuver">
                            <CheckCircle2 className="h-4 w-4" />
                          </button>
                        )}
                        {review.statut !== 'rejete' && (
                          <button type="button" onClick={() => void reject(review)} disabled={savingId === review.id} className="flex h-9 w-9 items-center justify-center rounded-lg text-amber-700 transition hover:bg-amber-50" title="Rejeter">
                            <EyeOff className="h-4 w-4" />
                          </button>
                        )}
                        <button type="button" onClick={() => void remove(review)} disabled={savingId === review.id} className="flex h-9 w-9 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50" title="Supprimer">
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
          onPrevious={() => void loadPage(page - 1, search, statusFilter)}
          onNext={() => void loadPage(page + 1, search, statusFilter)}
        />
      )}
    </AdminLayout>
  )
}

export default ReviewsPage
