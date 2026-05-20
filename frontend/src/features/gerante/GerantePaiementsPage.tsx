import { useCallback, useEffect, useRef, useState } from 'react'
import { apiClient } from '../../lib/apiClient'
import GeranteLayout from '../../layouts/GeranteLayout'
import type { LaravelPaginated, Paiement, PaymentType, PaymentStatus } from '../admin/payments/payments.types'

type QueryParams = {
  page?: number
  per_page?: number
  search?: string
  statut?: string
  type?: string
  date_from?: string
  date_to?: string
}

async function getGerantePaiements(params: QueryParams = {}): Promise<LaravelPaginated<Paiement>> {
  const response = await apiClient.get<{ data: LaravelPaginated<Paiement> }>('/gerante/paiements', { params })
  return response.data.data
}

const TYPE_LABELS: Record<PaymentType, string> = {
  acompte:      'Acompte',
  solde:        'Solde',
  complet:      'Complet',
  remboursement:'Remboursement',
  ajustement:   'Ajustement',
}

const STATUS_LABELS: Record<PaymentStatus, string> = {
  en_attente: 'En attente',
  valide:     'Validé',
  annule:     'Annulé',
  rembourse:  'Remboursé',
}

const TYPE_COLORS: Record<PaymentType, string> = {
  acompte:       'bg-blue-50 text-blue-700',
  solde:         'bg-green-50 text-green-700',
  complet:       'bg-emerald-50 text-emerald-700',
  remboursement: 'bg-red-50 text-red-700',
  ajustement:    'bg-amber-50 text-amber-700',
}

const STATUS_COLORS: Record<PaymentStatus, string> = {
  en_attente: 'bg-yellow-50 text-yellow-700',
  valide:     'bg-green-50 text-green-700',
  annule:     'bg-red-50 text-red-700',
  rembourse:  'bg-purple-50 text-purple-700',
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatAmount(v: number | string) {
  return Number(v).toLocaleString('fr-FR')
}

export default function GerantePaiementsPage() {
  const today = new Date().toISOString().slice(0, 10)
  const [items, setItems] = useState<LaravelPaginated<Paiement> | null>(null)
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [typeFilter, setTypeFilter] = useState('')
  const [statutFilter, setStatutFilter] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [error, setError] = useState<string | null>(null)

  const filtersReady = useRef(false)

  const paiements = items?.data ?? []

  const load = useCallback(async (
    p: number, s: string, t: string, st: string, df: string, dt: string,
  ) => {
    setLoading(true)
    setError(null)
    try {
      const data = await getGerantePaiements({
        page: p,
        per_page: 20,
        search: s || undefined,
        type: t || undefined,
        statut: st || undefined,
        date_from: df || undefined,
        date_to: dt || undefined,
      })
      setItems(data)
      setPage(p)
    } catch {
      setError('Impossible de charger les paiements.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load(1, search, typeFilter, statutFilter, dateFrom, dateTo)
    filtersReady.current = true
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!filtersReady.current) return
    void load(1, search, typeFilter, statutFilter, dateFrom, dateTo)
  }, [search, typeFilter, statutFilter, dateFrom, dateTo, load])

  const inputClass = 'rounded-xl border border-gray-200 bg-white px-3 py-2 text-[13px] outline-none focus:border-[#e91e63] focus:ring-2 focus:ring-[#e91e63]/20'

  return (
    <GeranteLayout>
    <div className="p-4 sm:p-6">
      <div className="mx-auto max-w-5xl">
        {/* En-tete */}
        <div className="mb-6">
          <h1 className="text-2xl font-black text-gray-900">Paiements</h1>
          <p className="mt-1 text-sm text-gray-500">Historique de tous les paiements (lecture seule)</p>
        </div>

        {/* Filtres */}
        <div className="mb-4 flex flex-wrap gap-2">
          <input
            className={inputClass}
            type="text"
            placeholder="Rechercher (recu, nom, telephone...)"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <select className={inputClass} value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)}>
            <option value="">Tous les types</option>
            {(Object.keys(TYPE_LABELS) as PaymentType[]).map((t) => (
              <option key={t} value={t}>{TYPE_LABELS[t]}</option>
            ))}
          </select>
          <select className={inputClass} value={statutFilter} onChange={(e) => setStatutFilter(e.target.value)}>
            <option value="">Tous les statuts</option>
            {(Object.keys(STATUS_LABELS) as PaymentStatus[]).map((s) => (
              <option key={s} value={s}>{STATUS_LABELS[s]}</option>
            ))}
          </select>
          <input className={inputClass} type="date" value={dateFrom} max={today} onChange={(e) => setDateFrom(e.target.value)} />
          <input className={inputClass} type="date" value={dateTo} max={today} onChange={(e) => setDateTo(e.target.value)} />
        </div>

        {error && <p className="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600">{error}</p>}

        {/* Liste */}
        <div className="rounded-2xl border border-gray-100 bg-white shadow-sm">
          {loading ? (
            <div className="flex items-center justify-center py-16">
              <div className="h-8 w-8 animate-spin rounded-full border-4 border-[#e91e63] border-t-transparent" />
            </div>
          ) : paiements.length === 0 ? (
            <p className="py-16 text-center text-sm text-gray-400">Aucun paiement trouve.</p>
          ) : (
            <div className="divide-y divide-gray-50">
              {paiements.map((p) => {
                const clientName = p.client
                  ? `${p.client.prenom} ${p.client.nom}`
                  : p.reservation?.client
                    ? `${p.reservation.client.prenom} ${p.reservation.client.nom}`
                    : '—'

                return (
                  <div key={p.id} className="flex items-center gap-4 px-5 py-4">
                    {/* Date + recu */}
                    <div className="w-28 shrink-0">
                      <p className="text-[12px] font-bold text-gray-800">{formatDate(p.date_paiement)}</p>
                      <p className="text-[11px] text-gray-400">{p.numero_recu}</p>
                    </div>

                    {/* Client + reservation */}
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[13px] font-semibold text-gray-900">{clientName}</p>
                      {p.reservation_id && (
                        <p className="text-[11px] text-gray-400">Resa #{p.reservation_id}</p>
                      )}
                    </div>

                    {/* Type */}
                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${TYPE_COLORS[p.type]}`}>
                      {TYPE_LABELS[p.type]}
                    </span>

                    {/* Montant */}
                    <p className="w-28 shrink-0 text-right text-[14px] font-black text-gray-900">
                      {formatAmount(p.montant)} <span className="text-[11px] font-normal text-gray-400">FCFA</span>
                    </p>

                    {/* Statut */}
                    <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${STATUS_COLORS[p.statut]}`}>
                      {STATUS_LABELS[p.statut]}
                    </span>
                  </div>
                )
              })}
            </div>
          )}
        </div>

        {/* Pagination */}
        {items && items.last_page > 1 && (
          <div className="mt-4 flex items-center justify-between">
            <p className="text-[12px] text-gray-400">
              Page {items.current_page} / {items.last_page} — {items.total} paiements
            </p>
            <div className="flex gap-2">
              <button
                disabled={items.current_page <= 1}
                onClick={() => void load(page - 1, search, typeFilter, statutFilter, dateFrom, dateTo)}
                className="rounded-xl border border-gray-200 px-3 py-1.5 text-[12px] font-semibold disabled:opacity-40 hover:bg-gray-50"
              >
                Precedent
              </button>
              <button
                disabled={items.current_page >= items.last_page}
                onClick={() => void load(page + 1, search, typeFilter, statutFilter, dateFrom, dateTo)}
                className="rounded-xl border border-gray-200 px-3 py-1.5 text-[12px] font-semibold disabled:opacity-40 hover:bg-gray-50"
              >
                Suivant
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
    </GeranteLayout>
  )
}
