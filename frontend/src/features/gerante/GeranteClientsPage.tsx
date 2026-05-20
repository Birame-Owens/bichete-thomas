import { useCallback, useEffect, useRef, useState } from 'react'
import { AlertCircle, Plus, RefreshCw, Search, UserRound, X } from 'lucide-react'
import GeranteLayout from '../../layouts/GeranteLayout'
import {
  createGeranteClient,
  getGeranteClients,
  updateGeranteClient,
  type GeranteClientForm,
} from './clients.api'
import type { Client, LaravelPaginated } from '../admin/clients/clients.types'

// ── Utilitaires ───────────────────────────────────────────────────────────────

function clientFullName(c: Client) {
  return `${c.prenom} ${c.nom}`.trim()
}

function emptyForm(): GeranteClientForm {
  return { nom: '', prenom: '', telephone: '', email: '' }
}

// ── Modal creation / edition ──────────────────────────────────────────────────

type ClientModalProps = {
  initial?: Client
  onClose: () => void
  onSaved: (client: Client) => void
}

function ClientModal({ initial, onClose, onSaved }: ClientModalProps) {
  const [form, setForm] = useState<GeranteClientForm>(
    initial
      ? { nom: initial.nom, prenom: initial.prenom, telephone: initial.telephone, email: initial.email ?? '' }
      : emptyForm(),
  )
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  const isEdit = Boolean(initial)

  function field(key: keyof GeranteClientForm) {
    return {
      value: form[key],
      onChange: (e: React.ChangeEvent<HTMLInputElement>) =>
        setForm((prev) => ({ ...prev, [key]: e.target.value })),
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setErrors({})
    setLoading(true)

    try {
      const saved = isEdit
        ? await updateGeranteClient(initial!.id, form)
        : await createGeranteClient(form)

      onSaved(saved)
    } catch (err: unknown) {
      const resp = (err as { response?: { data?: { errors?: Record<string, string[]> } } })?.response
      setErrors(resp?.data?.errors ?? {})
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
      <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <div className="mb-5 flex items-center justify-between">
          <h2 className="font-display text-lg font-bold text-gray-900">
            {isEdit ? 'Modifier la cliente' : 'Nouvelle cliente'}
          </h2>
          <button type="button" onClick={onClose} className="rounded-lg p-1 hover:bg-gray-100">
            <X className="h-5 w-5 text-gray-500" />
          </button>
        </div>

        <form onSubmit={(e) => void handleSubmit(e)} className="flex flex-col gap-4">
          <div className="grid grid-cols-2 gap-3">
            <LabeledInput label="Prenom" required {...field('prenom')} error={errors.prenom?.[0]} />
            <LabeledInput label="Nom" required {...field('nom')} error={errors.nom?.[0]} />
          </div>
          <LabeledInput
            label="Telephone (ex: +221771234567)"
            required
            {...field('telephone')}
            error={errors.telephone?.[0]}
            hint="Format international obligatoire"
          />
          <LabeledInput
            label="Email (optionnel)"
            type="email"
            {...field('email')}
            error={errors.email?.[0]}
          />

          {!isEdit && (
            <p className="rounded-lg bg-[#fff2f7] px-3 py-2 text-[12px] text-[#c41468]">
              La source sera automatiquement definie comme <strong>physique</strong> (comptoir).
            </p>
          )}

          <div className="flex gap-3 pt-1">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 rounded-xl border border-gray-200 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              Annuler
            </button>
            <button
              type="submit"
              disabled={loading}
              className="flex-1 rounded-xl bg-[#e91e63] py-2 text-sm font-semibold text-white hover:bg-[#c41468] disabled:opacity-60"
            >
              {loading ? 'Enregistrement...' : isEdit ? 'Enregistrer' : 'Creer'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

type LabeledInputProps = {
  label: string
  error?: string
  hint?: string
  required?: boolean
  type?: string
  value: string
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void
}

function LabeledInput({ label, error, hint, required, type = 'text', value, onChange }: LabeledInputProps) {
  return (
    <div className="flex flex-col gap-1">
      <label className="text-[12px] font-semibold text-gray-700">
        {label}
        {required && <span className="ml-0.5 text-red-500">*</span>}
      </label>
      <input
        type={type}
        required={required}
        value={value}
        onChange={onChange}
        className={[
          'rounded-xl border px-3 py-2 text-[13px] outline-none transition focus:ring-2 focus:ring-[#e91e63]/30',
          error ? 'border-red-400' : 'border-gray-200 focus:border-[#e91e63]',
        ].join(' ')}
      />
      {hint && !error && <p className="text-[11px] text-gray-400">{hint}</p>}
      {error && <p className="text-[11px] text-red-500">{error}</p>}
    </div>
  )
}

// ── Page principale ───────────────────────────────────────────────────────────

function GeranteClientsPage() {
  const [page, setPage] = useState<LaravelPaginated<Client> | null>(null)
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [modal, setModal] = useState<{ open: boolean; client?: Client }>({ open: false })
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const load = useCallback(async (q: string, pageNum = 1) => {
    setLoading(true)
    setError(null)

    try {
      const result = await getGeranteClients({ search: q || undefined, page: pageNum, per_page: 20 })
      setPage(result)
    } catch {
      setError('Impossible de charger les clientes.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load(search, 1)
  }, [load, search])

  function handleSearch(value: string) {
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      setSearch(value)
    }, 300)
  }

  function handleSaved(client: Client) {
    setModal({ open: false })
    // Mise a jour optimiste : on recharge la liste.
    void load(search, page?.current_page ?? 1)

    // Le client cree/modifie remonte en tete du prochain chargement (latest()).
    void client
  }

  return (
    <GeranteLayout>
      <div className="flex flex-col gap-5">
        {/* En-tete */}
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="font-display text-2xl font-bold text-gray-900">Clientes</h1>
            <p className="mt-0.5 text-[13px] text-gray-500">
              {page ? `${page.total} cliente${page.total > 1 ? 's' : ''}` : 'Chargement...'}
            </p>
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => void load(search, page?.current_page ?? 1)}
              className="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50"
              title="Actualiser"
            >
              <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            </button>
            <button
              type="button"
              onClick={() => setModal({ open: true })}
              className="flex items-center gap-2 rounded-xl bg-[#e91e63] px-4 py-2 text-[13px] font-semibold text-white hover:bg-[#c41468]"
            >
              <Plus className="h-4 w-4" />
              Nouvelle cliente
            </button>
          </div>
        </div>

        {/* Barre de recherche */}
        <div className="relative max-w-sm">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input
            type="search"
            placeholder="Nom, prenom, telephone..."
            onChange={(e) => handleSearch(e.target.value)}
            className="w-full rounded-xl border border-gray-200 py-2 pl-9 pr-3 text-[13px] outline-none focus:border-[#e91e63] focus:ring-2 focus:ring-[#e91e63]/20"
          />
        </div>

        {/* Erreur */}
        {error && (
          <div className="flex items-center gap-2 rounded-xl bg-red-50 px-4 py-3 text-[13px] text-red-700">
            <AlertCircle className="h-4 w-4 shrink-0" />
            {error}
          </div>
        )}

        {/* Tableau */}
        <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
          {/* Desktop */}
          <div className="hidden sm:block">
            <table className="w-full text-[13px]">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left font-semibold text-gray-500">Cliente</th>
                  <th className="px-4 py-3 text-left font-semibold text-gray-500">Telephone</th>
                  <th className="px-4 py-3 text-left font-semibold text-gray-500">Email</th>
                  <th className="px-4 py-3 text-left font-semibold text-gray-500">Source</th>
                  <th className="px-4 py-3 text-left font-semibold text-gray-500">Statut</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {page?.data.length === 0 && !loading && (
                  <tr>
                    <td colSpan={6} className="px-4 py-10 text-center text-gray-400">
                      Aucune cliente trouvee.
                    </td>
                  </tr>
                )}
                {page?.data.map((client) => (
                  <tr key={client.id} className="hover:bg-gray-50/50">
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#fff2f7]">
                          <UserRound className="h-4 w-4 text-[#e91e63]" />
                        </div>
                        <span className="font-semibold text-gray-900">{clientFullName(client)}</span>
                      </div>
                    </td>
                    <td className="px-4 py-3 font-mono text-gray-700">{client.telephone}</td>
                    <td className="px-4 py-3 text-gray-600">{client.email ?? <span className="text-gray-300">—</span>}</td>
                    <td className="px-4 py-3">
                      <SourceBadge source={client.source} />
                    </td>
                    <td className="px-4 py-3">
                      {client.est_blackliste ? (
                        <span className="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700">
                          Blacklistee
                        </span>
                      ) : (
                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                          Active
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button
                        type="button"
                        onClick={() => setModal({ open: true, client })}
                        className="rounded-lg border border-gray-200 px-3 py-1 text-[12px] font-semibold text-gray-700 hover:bg-gray-50"
                      >
                        Modifier
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile cards */}
          <div className="flex flex-col divide-y divide-gray-100 sm:hidden">
            {page?.data.length === 0 && !loading && (
              <p className="px-4 py-10 text-center text-[13px] text-gray-400">Aucune cliente trouvee.</p>
            )}
            {page?.data.map((client) => (
              <div key={client.id} className="flex items-start gap-3 px-4 py-3">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#fff2f7]">
                  <UserRound className="h-4 w-4 text-[#e91e63]" />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate font-semibold text-gray-900">{clientFullName(client)}</p>
                  <p className="text-[12px] font-mono text-gray-500">{client.telephone}</p>
                  {client.email && <p className="text-[12px] text-gray-400">{client.email}</p>}
                  <div className="mt-1 flex items-center gap-2">
                    <SourceBadge source={client.source} />
                    {client.est_blackliste && (
                      <span className="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700">
                        Blacklistee
                      </span>
                    )}
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setModal({ open: true, client })}
                  className="shrink-0 rounded-lg border border-gray-200 px-3 py-1 text-[12px] font-semibold text-gray-700"
                >
                  Modifier
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Pagination */}
        {page && page.last_page > 1 && (
          <div className="flex items-center justify-between text-[13px]">
            <p className="text-gray-500">
              Page {page.current_page} / {page.last_page}
            </p>
            <div className="flex gap-2">
              <button
                type="button"
                disabled={page.current_page === 1}
                onClick={() => void load(search, page.current_page - 1)}
                className="rounded-xl border border-gray-200 px-4 py-1.5 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40"
              >
                Precedent
              </button>
              <button
                type="button"
                disabled={page.current_page === page.last_page}
                onClick={() => void load(search, page.current_page + 1)}
                className="rounded-xl border border-gray-200 px-4 py-1.5 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40"
              >
                Suivant
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modal */}
      {modal.open && (
        <ClientModal
          initial={modal.client}
          onClose={() => setModal({ open: false })}
          onSaved={handleSaved}
        />
      )}
    </GeranteLayout>
  )
}

function SourceBadge({ source }: { source: string }) {
  if (source === 'physique') {
    return (
      <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
        Physique
      </span>
    )
  }

  return (
    <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
      En ligne
    </span>
  )
}

export default GeranteClientsPage
