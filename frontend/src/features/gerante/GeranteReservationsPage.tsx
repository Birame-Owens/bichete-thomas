import { useCallback, useEffect, useRef, useState } from 'react'
import { AlertTriangle, CalendarDays, Clock3, RefreshCw, Scissors, Search, UserRound } from 'lucide-react'
import GeranteLayout from '../../layouts/GeranteLayout'
import { getGeranteReservations, updateGeranteReservationStatus, type SoldeInfo } from './reservations.api'
import type { LaravelPaginated, Reservation, ReservationStatus } from '../admin/reservations/reservations.types'

// Miroir exact de TRANSITIONS dans Gerante/ReservationController.php.
const TRANSITIONS: Record<ReservationStatus, ReservationStatus[]> = {
  en_attente:   ['confirmee', 'annulee', 'absence'],
  confirmee:    ['en_cours', 'annulee', 'absence'],
  acompte_paye: ['en_cours', 'terminee', 'annulee', 'absence'],
  en_cours:     ['terminee', 'annulee', 'absence'],
  terminee:     [],
  annulee:      [],
  absence:      [],
}

// Transitions qui exigent une raison (acompte déjà encaissé).
const SENSITIVE: Partial<Record<ReservationStatus, ReservationStatus[]>> = {
  acompte_paye: ['annulee', 'absence'],
}

const STATUS_LABELS: Record<ReservationStatus, string> = {
  en_attente:   'En attente',
  confirmee:    'Confirmee',
  acompte_paye: 'Acompte paye',
  en_cours:     'En cours',
  terminee:     'Terminee',
  annulee:      'Annulee',
  absence:      'Absence',
}

function statusClass(status: ReservationStatus) {
  if (status === 'terminee') return 'bg-emerald-50 text-emerald-700'
  if (status === 'annulee' || status === 'absence') return 'bg-red-50 text-red-700'
  if (status === 'acompte_paye' || status === 'en_cours' || status === 'confirmee') return 'bg-[#fff2f7] text-[#c41468]'
  return 'bg-gray-100 text-gray-600'
}

function money(value: number | string | null | undefined) {
  const n = Number(value || 0)
  return `${n.toLocaleString('fr-FR')} FCFA`
}

function formatDate(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }).format(date)
}

function clientName(r: Reservation) {
  if (!r.client) return 'Cliente inconnue'
  return `${r.client.prenom} ${r.client.nom}`.trim()
}

// ── Modal encaissement solde (passage en terminee avec montant restant) ───────

type SoldeModalProps = {
  reservation: Reservation
  onConfirm: (solde: SoldeInfo) => void
  onCancel: () => void
  saving: boolean
}

const PAYMENT_MODES = [
  { value: 'especes',       label: 'Espèces' },
  { value: 'wave',          label: 'Wave' },
  { value: 'orange_money',  label: 'Orange Money' },
  { value: 'carte_bancaire',label: 'Carte bancaire' },
  { value: 'autre',         label: 'Autre' },
]

function SoldeModal({ reservation, onConfirm, onCancel, saving }: SoldeModalProps) {
  const [mode, setMode] = useState('especes')
  const montant = Number(reservation.montant_restant || 0)

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
      <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
        <h2 className="text-lg font-black text-gray-950">Encaissement du solde</h2>
        <p className="mt-1 text-sm font-semibold text-gray-500">
          {reservation.client ? `${reservation.client.prenom} ${reservation.client.nom} — ` : ''}
          Reste à encaisser avant de terminer.
        </p>

        <div className="mt-4 rounded-xl bg-[#fff8fb] px-4 py-3 text-center">
          <p className="text-[10px] font-black uppercase tracking-widest text-[#c41468]">Montant restant</p>
          <p className="mt-1 text-2xl font-black text-gray-950">
            {montant.toLocaleString('fr-FR')} {reservation.devise ?? 'FCFA'}
          </p>
        </div>

        <div className="mt-4">
          <label className="block text-[11px] font-black uppercase tracking-wide text-gray-500">
            Mode de paiement
          </label>
          <select
            value={mode}
            onChange={(e) => setMode(e.target.value)}
            className="mt-1.5 h-11 w-full rounded-xl border border-gray-200 px-3 text-sm font-bold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
          >
            {PAYMENT_MODES.map((m) => (
              <option key={m.value} value={m.value}>{m.label}</option>
            ))}
          </select>
        </div>

        <div className="mt-5 grid gap-2">
          <button
            type="button"
            disabled={saving}
            onClick={() => onConfirm({ enregistrer_paiement: true, mode_paiement_solde: mode })}
            className="w-full rounded-xl bg-[#e91e63] py-3 text-sm font-black text-white transition hover:bg-[#c41468] disabled:opacity-60"
          >
            {saving ? 'Enregistrement...' : 'Encaisser + Terminer'}
          </button>
          <button
            type="button"
            disabled={saving}
            onClick={() => onConfirm({ enregistrer_paiement: false })}
            className="w-full rounded-xl bg-gray-100 py-3 text-sm font-black text-gray-700 transition hover:bg-gray-200 disabled:opacity-60"
          >
            Terminer sans enregistrer
          </button>
          <button
            type="button"
            disabled={saving}
            onClick={onCancel}
            className="w-full rounded-xl py-2 text-sm font-semibold text-gray-400 transition hover:text-gray-600"
          >
            Annuler
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Modal raison (transitions sensibles post-acompte) ──────────────────────

type RaisonModalProps = {
  reservation: Reservation
  targetStatus: ReservationStatus
  onConfirm: (raison: string) => void
  onCancel: () => void
  saving: boolean
}

function RaisonModal({ reservation, targetStatus, onConfirm, onCancel, saving }: RaisonModalProps) {
  const [raison, setRaison] = useState('')
  const MIN = 20

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div className="flex items-start gap-3">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50">
            <AlertTriangle className="h-5 w-5 text-red-600" />
          </div>
          <div>
            <h2 className="text-base font-black text-gray-950">Raison obligatoire</h2>
            <p className="mt-1 text-sm font-semibold text-gray-500">
              Un acompte de {money(reservation.montant_acompte)} a deja ete encaisse.
              Expliquez pourquoi vous passez cette reservation en{' '}
              <span className="font-black text-red-600">{STATUS_LABELS[targetStatus]}</span>.
            </p>
          </div>
        </div>

        <div className="mt-5">
          <label className="block text-[11px] font-black uppercase tracking-wide text-gray-500">
            Raison ({raison.length}/{MIN} min.)
          </label>
          <textarea
            rows={4}
            value={raison}
            onChange={(e) => setRaison(e.target.value)}
            placeholder="Ex : La cliente a annule par WhatsApp ce matin, accord valide avec la patronne..."
            className="mt-1.5 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
          />
          {raison.length > 0 && raison.length < MIN && (
            <p className="mt-1 text-xs font-bold text-red-500">
              Encore {MIN - raison.length} caractere(s) requis.
            </p>
          )}
        </div>

        <div className="mt-5 grid gap-2">
          <button
            type="button"
            disabled={saving || raison.length < MIN}
            onClick={() => onConfirm(raison)}
            className="w-full rounded-xl bg-red-600 py-3 text-sm font-black text-white transition hover:bg-red-700 disabled:opacity-50"
          >
            {saving ? 'Enregistrement...' : `Confirmer — ${STATUS_LABELS[targetStatus]}`}
          </button>
          <button
            type="button"
            disabled={saving}
            onClick={onCancel}
            className="w-full rounded-xl bg-gray-100 py-3 text-sm font-black text-gray-700 transition hover:bg-gray-200"
          >
            Annuler
          </button>
        </div>
      </div>
    </div>
  )
}

// ── Page principale ────────────────────────────────────────────────────────

function GeranteReservationsPage() {
  const today = new Date().toISOString().slice(0, 10)
  const [items, setItems] = useState<LaravelPaginated<Reservation> | null>(null)
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [dateFrom, setDateFrom] = useState(today)
  const [dateTo, setDateTo] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  // Etat pour la modal raison (annulation post-acompte)
  const [sensitiveTarget, setSensitiveTarget] = useState<{
    reservation: Reservation
    status: ReservationStatus
  } | null>(null)
  // Etat pour la modal solde (terminee avec montant restant)
  const [soldeTarget, setSoldeTarget] = useState<Reservation | null>(null)
  const [saving, setSaving] = useState(false)

  const filtersReady = useRef(false)

  const reservations = items?.data ?? []

  const load = useCallback(async (
    nextPage: number,
    nextSearch: string,
    nextStatus: string,
    nextFrom: string,
    nextTo: string,
  ) => {
    setLoading(true)
    setError(null)
    try {
      const data = await getGeranteReservations({
        page: nextPage,
        per_page: 20,
        search: nextSearch || undefined,
        statut: nextStatus === 'all' ? undefined : nextStatus,
        date_from: nextFrom || undefined,
        date_to: nextTo || undefined,
      })
      setItems(data)
      setPage(nextPage)
    } catch {
      setError('Impossible de charger les reservations.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load(1, '', 'all', today, '')
  }, [load, today])

  useEffect(() => {
    if (!filtersReady.current) {
      filtersReady.current = true
      return
    }
    const id = window.setTimeout(() => {
      void load(1, search, statusFilter, dateFrom, dateTo)
    }, 300)
    return () => window.clearTimeout(id)
  }, [dateTo, dateFrom, load, search, statusFilter])

  const applyStatus = async (
    reservation: Reservation,
    status: ReservationStatus,
    raison?: string,
    solde?: SoldeInfo,
  ) => {
    setSaving(true)
    try {
      await updateGeranteReservationStatus(reservation.id, status, raison, solde)
      setSuccess(`Reservation #${reservation.id} → ${STATUS_LABELS[status]}.`)
      setSensitiveTarget(null)
      setSoldeTarget(null)
      await load(page, search, statusFilter, dateFrom, dateTo)
    } catch {
      setError('Changement de statut impossible. Verifiez votre connexion.')
    } finally {
      setSaving(false)
    }
  }

  const handleStatusChange = (reservation: Reservation, status: ReservationStatus) => {
    // Passage en terminee avec un solde restant → modal encaissement
    if (status === 'terminee' && Number(reservation.montant_restant) > 0) {
      setSoldeTarget(reservation)
      return
    }
    // Annulation/absence post-acompte → modal raison obligatoire
    const isSensitive = (SENSITIVE[reservation.statut] ?? []).includes(status)
    if (isSensitive) {
      setSensitiveTarget({ reservation, status })
      return
    }
    void applyStatus(reservation, status)
  }

  return (
    <GeranteLayout>
      {/* Modal encaissement solde avant marquage terminee */}
      {soldeTarget && (
        <SoldeModal
          reservation={soldeTarget}
          onConfirm={(solde) => void applyStatus(soldeTarget, 'terminee', undefined, solde)}
          onCancel={() => setSoldeTarget(null)}
          saving={saving}
        />
      )}

      {/* Modal raison pour transitions post-acompte */}
      {sensitiveTarget && (
        <RaisonModal
          reservation={sensitiveTarget.reservation}
          targetStatus={sensitiveTarget.status}
          onConfirm={(raison) => void applyStatus(sensitiveTarget.reservation, sensitiveTarget.status, raison)}
          onCancel={() => setSensitiveTarget(null)}
          saving={saving}
        />
      )}

      {/* En-tete */}
      <div className="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#e91e63]">Espace gerante</p>
          <h1 className="mt-2 text-2xl font-black text-[#111018] sm:text-3xl">Reservations du jour</h1>
          <p className="mt-2 text-sm font-medium text-gray-500">
            Suivez et mettez a jour les statuts. Pour toute annulation apres acompte, une raison est obligatoire.
          </p>
        </div>
        <button
          type="button"
          onClick={() => void load(page, search, statusFilter, dateFrom, dateTo)}
          className="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50"
        >
          <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          Actualiser
        </button>
      </div>

      {/* Filtres */}
      <section className="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <div className="grid gap-3 xl:grid-cols-[1fr_auto]">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
              placeholder="Rechercher par nom, telephone..."
            />
          </div>
          <div className="grid gap-2 sm:flex">
            <input
              type="date"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              className="h-11 rounded-xl border border-gray-200 px-3 text-sm font-bold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
            />
            <input
              type="date"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              className="h-11 rounded-xl border border-gray-200 px-3 text-sm font-bold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
            />
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="h-11 rounded-xl border border-gray-200 px-3 text-sm font-bold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
            >
              <option value="all">Tous statuts</option>
              {(Object.keys(STATUS_LABELS) as ReservationStatus[]).map((s) => (
                <option key={s} value={s}>{STATUS_LABELS[s]}</option>
              ))}
            </select>
          </div>
        </div>
      </section>

      {/* Alertes */}
      {error && (
        <div className="mb-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
          {error}
        </div>
      )}
      {success && (
        <div className="mb-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">
          {success}
        </div>
      )}

      {/* Cards mobile */}
      <section className="grid gap-3 lg:hidden">
        {loading ? (
          Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="rounded-xl border border-gray-100 bg-white p-4">
              <div className="h-5 w-2/3 animate-pulse rounded bg-gray-100" />
              <div className="mt-3 h-4 w-full animate-pulse rounded bg-gray-100" />
              <div className="mt-2 h-4 w-1/2 animate-pulse rounded bg-gray-100" />
            </div>
          ))
        ) : reservations.length === 0 ? (
          <div className="rounded-xl border border-gray-100 bg-white px-4 py-10 text-center text-sm font-bold text-gray-400">
            Aucune reservation pour cette periode.
          </div>
        ) : (
          reservations.map((r) => (
            <article key={r.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate font-black text-gray-950">{clientName(r)}</p>
                  <p className="mt-0.5 text-sm font-semibold text-gray-500">
                    {formatDate(r.date_reservation)} — {r.heure_debut.slice(0, 5)}
                  </p>
                </div>
                <span className={`shrink-0 rounded-full px-3 py-1 text-xs font-black ${statusClass(r.statut)}`}>
                  {STATUS_LABELS[r.statut]}
                </span>
              </div>
              <div className="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-gray-500">
                <span className="inline-flex items-center gap-1"><Scissors className="h-3.5 w-3.5" />{r.coiffeuse ? `${r.coiffeuse.prenom} ${r.coiffeuse.nom}` : 'Non assignee'}</span>
                <span className="inline-flex items-center gap-1 text-[#c41468]"><CalendarDays className="h-3.5 w-3.5" />{money(r.montant_acompte)} acompte</span>
              </div>
              {TRANSITIONS[r.statut].length > 0 && (
                <div className="mt-4 flex flex-wrap gap-2">
                  {TRANSITIONS[r.statut].map((s) => (
                    <button
                      key={s}
                      type="button"
                      disabled={saving}
                      onClick={() => handleStatusChange(r, s)}
                      className="rounded-lg bg-gray-50 px-3 py-1.5 text-xs font-black text-gray-700 transition hover:bg-gray-100 disabled:opacity-50"
                    >
                      → {STATUS_LABELS[s]}
                    </button>
                  ))}
                </div>
              )}
            </article>
          ))
        )}
      </section>

      {/* Tableau desktop */}
      <section className="hidden overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm lg:block">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px] text-left text-sm">
            <thead className="bg-gray-50 text-xs font-black uppercase tracking-[0.12em] text-gray-500">
              <tr>
                <th className="px-5 py-3">Heure</th>
                <th className="px-5 py-3">Cliente</th>
                <th className="px-5 py-3">Coiffeuse</th>
                <th className="px-5 py-3">Prestation</th>
                <th className="px-5 py-3">Acompte</th>
                <th className="px-5 py-3">Statut actuel</th>
                <th className="px-5 py-3">Passer a</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                Array.from({ length: 5 }).map((_, row) => (
                  <tr key={row}>
                    {Array.from({ length: 7 }).map((__, cell) => (
                      <td key={cell} className="px-5 py-4">
                        <div className="h-5 animate-pulse rounded bg-gray-100" />
                      </td>
                    ))}
                  </tr>
                ))
              ) : reservations.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-10 text-center text-sm font-bold text-gray-400">
                    Aucune reservation pour cette periode.
                  </td>
                </tr>
              ) : (
                reservations.map((r) => (
                  <tr key={r.id} className="transition hover:bg-[#fff8fb]">
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-2 font-black text-gray-950">
                        <Clock3 className="h-4 w-4 text-[#e91e63]" />
                        {r.heure_debut.slice(0, 5)}
                      </div>
                      <div className="mt-0.5 text-xs font-bold text-gray-400">{formatDate(r.date_reservation)}</div>
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-2 font-black text-gray-950">
                        <UserRound className="h-4 w-4 text-gray-400" />
                        {clientName(r)}
                      </div>
                      <div className="mt-0.5 text-xs font-bold text-gray-400">{r.client?.telephone ?? '-'}</div>
                    </td>
                    <td className="px-5 py-4 font-semibold text-gray-500">
                      {r.coiffeuse ? `${r.coiffeuse.prenom} ${r.coiffeuse.nom}` : 'Non assignee'}
                    </td>
                    <td className="px-5 py-4 font-semibold text-gray-600">
                      {r.details?.[0]?.coiffure_nom ?? '-'}
                      {(r.details?.length ?? 0) > 1 && (
                        <span className="ml-1 text-xs text-gray-400">+{(r.details?.length ?? 1) - 1}</span>
                      )}
                    </td>
                    <td className="px-5 py-4">
                      <div className="font-black text-[#c41468]">{money(r.montant_acompte)}</div>
                      <div className="text-xs font-bold text-gray-400">/ {money(r.montant_total)}</div>
                    </td>
                    <td className="px-5 py-4">
                      <span className={`inline-block rounded-full px-3 py-1 text-xs font-black ${statusClass(r.statut)}`}>
                        {STATUS_LABELS[r.statut]}
                      </span>
                    </td>
                    <td className="px-5 py-4">
                      {TRANSITIONS[r.statut].length === 0 ? (
                        <span className="text-xs font-bold text-gray-300">Etat final</span>
                      ) : (
                        <div className="flex flex-wrap gap-1">
                          {TRANSITIONS[r.statut].map((s) => {
                            const isSensitive = (SENSITIVE[r.statut] ?? []).includes(s)
                            return (
                              <button
                                key={s}
                                type="button"
                                disabled={saving}
                                onClick={() => handleStatusChange(r, s)}
                                className={[
                                  'rounded-lg px-2.5 py-1 text-xs font-black transition disabled:opacity-50',
                                  isSensitive
                                    ? 'bg-red-50 text-red-700 hover:bg-red-100'
                                    : 'bg-gray-50 text-gray-700 hover:bg-gray-100',
                                ].join(' ')}
                                title={isSensitive ? 'Une raison sera demandee' : undefined}
                              >
                                {STATUS_LABELS[s]}
                                {isSensitive && <AlertTriangle className="ml-1 inline h-3 w-3" />}
                              </button>
                            )
                          })}
                        </div>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>

      {/* Pagination */}
      {items && items.last_page > 1 && (
        <div className="mt-5 flex items-center justify-between text-sm font-bold text-gray-500">
          <span>{items.total} reservation(s)</span>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={page <= 1 || loading}
              onClick={() => void load(page - 1, search, statusFilter, dateFrom, dateTo)}
              className="rounded-lg border border-gray-200 bg-white px-4 py-2 transition hover:bg-gray-50 disabled:opacity-40"
            >
              Precedent
            </button>
            <span className="flex items-center px-2">{page} / {items.last_page}</span>
            <button
              type="button"
              disabled={page >= items.last_page || loading}
              onClick={() => void load(page + 1, search, statusFilter, dateFrom, dateTo)}
              className="rounded-lg border border-gray-200 bg-white px-4 py-2 transition hover:bg-gray-50 disabled:opacity-40"
            >
              Suivant
            </button>
          </div>
        </div>
      )}
    </GeranteLayout>
  )
}

export default GeranteReservationsPage
