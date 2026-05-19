import { useCallback, useEffect, useRef, useState } from 'react'
import { AlertTriangle, ChevronDown, ChevronRight, RefreshCw, Search, ShieldAlert, X } from 'lucide-react'
import AdminLayout from '../../../layouts/AdminLayout'
import { getLogs } from './logs.api'
import type { LaravelPaginated, LogSysteme, LogQueryParams } from './logs.types'

// ─── Constantes ──────────────────────────────────────────────────────────────

const ACTION_GERANTE_ALERT = 'alerte_gerante_annulation_depot'

const MODULE_LABELS: Record<string, string> = {
  reservations: 'Reservations',
  paiements: 'Paiements',
  clients: 'Clients',
  caisse: 'Caisse',
  coiffures: 'Coiffures',
  auth: 'Authentification',
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatDate(iso: string) {
  const d = new Date(iso)
  return d.toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function actorRole(log: LogSysteme): 'gerante' | 'admin' | null {
  if (log.metadata && typeof log.metadata === 'object' && 'actor_role' in log.metadata) {
    return (log.metadata as Record<string, string>).actor_role as 'gerante' | 'admin'
  }
  return null
}

function ActorBadge({ log }: { log: LogSysteme }) {
  const role = actorRole(log)
  if (role === 'gerante') {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-bold text-purple-700">
        Gerante
      </span>
    )
  }
  if (role === 'admin') {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-bold text-blue-700">
        Admin
      </span>
    )
  }
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-500">
      Systeme
    </span>
  )
}

function ActionBadge({ action }: { action: string }) {
  const isAlert = action === ACTION_GERANTE_ALERT
  if (isAlert) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-700">
        <AlertTriangle className="h-3 w-3" />
        {action}
      </span>
    )
  }
  return (
    <span className="inline-block max-w-[220px] truncate rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-mono text-gray-600">
      {action}
    </span>
  )
}

function JsonAccordion({ label, data }: { label: string; data: Record<string, unknown> | null }) {
  const [open, setOpen] = useState(false)
  if (!data || Object.keys(data).length === 0) return <span className="text-gray-300">—</span>
  return (
    <div>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="inline-flex items-center gap-1 text-[11px] text-gray-500 hover:text-gray-800"
      >
        {open ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
        {label}
      </button>
      {open && (
        <pre className="mt-1 max-h-48 overflow-auto rounded-lg bg-gray-50 p-2 text-[10px] text-gray-700">
          {JSON.stringify(data, null, 2)}
        </pre>
      )}
    </div>
  )
}

// Extrait la raison depuis les métadonnées des alertes gérante.
function RaisonCell({ log }: { log: LogSysteme }) {
  if (log.action !== ACTION_GERANTE_ALERT) return null
  const raison = log.metadata && typeof log.metadata === 'object' ? (log.metadata as Record<string, string>).raison : null
  if (!raison) return null
  return (
    <p className="mt-1 rounded-lg border border-red-100 bg-red-50 px-2 py-1 text-[11px] text-red-800 italic">
      "{raison}"
    </p>
  )
}

// ─── Composants UI ────────────────────────────────────────────────────────────

const inputClass =
  'w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 placeholder-gray-400 shadow-sm outline-none focus:border-[#e91e63] focus:ring-2 focus:ring-[#e91e63]/20 transition'

function Pagination({
  page,
  lastPage,
  total,
  onPageChange,
}: {
  page: number
  lastPage: number
  total: number
  onPageChange: (p: number) => void
}) {
  if (lastPage <= 1) return null
  return (
    <div className="flex items-center justify-between text-sm text-gray-500">
      <span>{total} entrée{total > 1 ? 's' : ''}</span>
      <div className="flex gap-1">
        <button
          type="button"
          disabled={page <= 1}
          onClick={() => onPageChange(page - 1)}
          className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold disabled:opacity-40"
        >
          Préc.
        </button>
        <span className="flex items-center px-3 text-xs">
          {page} / {lastPage}
        </span>
        <button
          type="button"
          disabled={page >= lastPage}
          onClick={() => onPageChange(page + 1)}
          className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold disabled:opacity-40"
        >
          Suiv.
        </button>
      </div>
    </div>
  )
}

// ─── Page ─────────────────────────────────────────────────────────────────────

type Tab = 'tous' | 'alertes'

export default function LogsPage() {
  const [tab, setTab] = useState<Tab>('tous')
  const [logs, setLogs] = useState<LaravelPaginated<LogSysteme> | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [search, setSearch] = useState('')
  const [module, setModule] = useState('')
  const [dateDebut, setDateDebut] = useState('')
  const [dateFin, setDateFin] = useState('')
  const [page, setPage] = useState(1)

  const abortRef = useRef<AbortController | null>(null)

  const fetchLogs = useCallback(
    async (p = 1) => {
      abortRef.current?.abort()
      abortRef.current = new AbortController()

      setLoading(true)
      setError(null)

      try {
        const params: LogQueryParams = { page: p, per_page: 25 }
        if (tab === 'alertes') params.action = ACTION_GERANTE_ALERT
        if (search.trim()) params.search = search.trim()
        if (module) params.module = module
        if (dateDebut) params.date_debut = dateDebut
        if (dateFin) params.date_fin = dateFin

        const data = await getLogs(params)
        setLogs(data)
        setPage(p)
      } catch (err: unknown) {
        if (err instanceof Error && err.name !== 'CanceledError') {
          setError('Impossible de charger les logs.')
        }
      } finally {
        setLoading(false)
      }
    },
    [tab, search, module, dateDebut, dateFin],
  )

  // Rechargement quand les filtres ou l'onglet changent.
  useEffect(() => {
    void fetchLogs(1)
  }, [fetchLogs])

  const resetFilters = () => {
    setSearch('')
    setModule('')
    setDateDebut('')
    setDateFin('')
    setPage(1)
  }

  const hasActiveFilters = search || module || dateDebut || dateFin

  return (
    <AdminLayout>
      <div className="space-y-5">
        {/* En-tête */}
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="font-display text-2xl font-bold text-gray-900">Logs systeme</h1>
            <p className="mt-0.5 text-sm text-gray-500">
              Historique des actions admin et gerante
            </p>
          </div>
          <button
            type="button"
            onClick={() => fetchLogs(page)}
            disabled={loading}
            className="inline-flex items-center gap-2 rounded-xl bg-white border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50"
          >
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            Actualiser
          </button>
        </div>

        {/* Onglets */}
        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => { setTab('tous'); setPage(1) }}
            className={[
              'rounded-xl px-4 py-2 text-sm font-semibold transition',
              tab === 'tous'
                ? 'bg-[#e91e63] text-white shadow-sm'
                : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50',
            ].join(' ')}
          >
            Tous les logs
          </button>
          <button
            type="button"
            onClick={() => { setTab('alertes'); setPage(1) }}
            className={[
              'inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition',
              tab === 'alertes'
                ? 'bg-red-600 text-white shadow-sm'
                : 'bg-white border border-gray-200 text-red-600 hover:bg-red-50',
            ].join(' ')}
          >
            <ShieldAlert className="h-4 w-4" />
            Alertes gerante
          </button>
        </div>

        {/* Filtres */}
        <div className="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                placeholder="Rechercher..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className={`${inputClass} pl-9`}
              />
            </div>

            <select
              value={module}
              onChange={(e) => setModule(e.target.value)}
              className={inputClass}
            >
              <option value="">Tous les modules</option>
              {Object.entries(MODULE_LABELS).map(([val, label]) => (
                <option key={val} value={val}>{label}</option>
              ))}
            </select>

            <input
              type="date"
              value={dateDebut}
              onChange={(e) => setDateDebut(e.target.value)}
              className={inputClass}
              placeholder="Du"
            />
            <input
              type="date"
              value={dateFin}
              onChange={(e) => setDateFin(e.target.value)}
              className={inputClass}
              placeholder="Au"
            />
          </div>

          {hasActiveFilters && (
            <button
              type="button"
              onClick={resetFilters}
              className="mt-3 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800"
            >
              <X className="h-3.5 w-3.5" />
              Effacer les filtres
            </button>
          )}
        </div>

        {/* Contenu */}
        {error && (
          <div className="rounded-2xl border border-red-100 bg-red-50 px-5 py-4 text-sm text-red-700">
            {error}
          </div>
        )}

        {!error && (
          <div className="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            {tab === 'alertes' && !loading && logs && logs.total === 0 && (
              <div className="flex flex-col items-center gap-2 py-16 text-center">
                <ShieldAlert className="h-8 w-8 text-green-400" />
                <p className="text-sm font-semibold text-gray-600">Aucune alerte gérante</p>
                <p className="text-xs text-gray-400">Aucune annulation d'acompte enregistrée.</p>
              </div>
            )}

            {loading && (
              <div className="flex items-center justify-center py-16 text-sm text-gray-400">
                Chargement...
              </div>
            )}

            {!loading && logs && logs.data.length > 0 && (
              <>
                {/* Desktop table */}
                <div className="hidden md:block overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-gray-100 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th className="px-4 py-3 text-left">Date</th>
                        <th className="px-4 py-3 text-left">Utilisateur</th>
                        <th className="px-4 py-3 text-left">Action</th>
                        <th className="px-4 py-3 text-left">Module</th>
                        <th className="px-4 py-3 text-left">Description / Raison</th>
                        <th className="px-4 py-3 text-left">Avant / Après</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                      {logs.data.map((log) => (
                        <tr
                          key={log.id}
                          className={[
                            'transition hover:bg-gray-50',
                            log.action === ACTION_GERANTE_ALERT ? 'bg-red-50/40' : '',
                          ].join(' ')}
                        >
                          <td className="whitespace-nowrap px-4 py-3 text-xs text-gray-500">
                            {formatDate(log.created_at)}
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex flex-col gap-0.5">
                              <span className="text-xs font-semibold text-gray-800">
                                {log.user?.name ?? 'Systeme'}
                              </span>
                              <ActorBadge log={log} />
                            </div>
                          </td>
                          <td className="px-4 py-3">
                            <ActionBadge action={log.action} />
                          </td>
                          <td className="px-4 py-3 text-xs text-gray-600">
                            {log.module ? (MODULE_LABELS[log.module] ?? log.module) : '—'}
                          </td>
                          <td className="px-4 py-3 max-w-xs">
                            <p className="text-xs text-gray-700">{log.description ?? '—'}</p>
                            <RaisonCell log={log} />
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex flex-col gap-1">
                              <JsonAccordion label="Avant" data={log.before} />
                              <JsonAccordion label="Après" data={log.after} />
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Mobile cards */}
                <div className="divide-y divide-gray-100 md:hidden">
                  {logs.data.map((log) => (
                    <div
                      key={log.id}
                      className={[
                        'px-4 py-4 space-y-2',
                        log.action === ACTION_GERANTE_ALERT ? 'bg-red-50/50' : '',
                      ].join(' ')}
                    >
                      <div className="flex items-start justify-between gap-2">
                        <ActionBadge action={log.action} />
                        <span className="text-[11px] text-gray-400">{formatDate(log.created_at)}</span>
                      </div>
                      <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-gray-800">
                          {log.user?.name ?? 'Systeme'}
                        </span>
                        <ActorBadge log={log} />
                        {log.module && (
                          <span className="text-[11px] text-gray-400">
                            · {MODULE_LABELS[log.module] ?? log.module}
                          </span>
                        )}
                      </div>
                      {log.description && (
                        <p className="text-xs text-gray-600">{log.description}</p>
                      )}
                      <RaisonCell log={log} />
                      <div className="flex gap-3">
                        <JsonAccordion label="Avant" data={log.before} />
                        <JsonAccordion label="Après" data={log.after} />
                      </div>
                    </div>
                  ))}
                </div>

                <div className="border-t border-gray-100 px-4 py-3">
                  <Pagination
                    page={page}
                    lastPage={logs.last_page}
                    total={logs.total}
                    onPageChange={(p) => fetchLogs(p)}
                  />
                </div>
              </>
            )}

            {!loading && logs && logs.data.length === 0 && tab !== 'alertes' && (
              <div className="flex flex-col items-center gap-2 py-16 text-center text-sm text-gray-400">
                Aucun log correspondant aux filtres.
              </div>
            )}
          </div>
        )}
      </div>
    </AdminLayout>
  )
}
