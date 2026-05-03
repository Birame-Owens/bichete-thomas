import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { Edit, Eye, EyeOff, Mail, Plus, RefreshCw, Search, ShieldCheck, Trash2 } from 'lucide-react'
import PersonnelLayout from './components/PersonnelLayout'
import {
  EmptyState,
  ErrorState,
  FormField,
  Modal,
  Pagination,
  StatusBadge,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from './components/PersonnelUi'
import {
  createGerante,
  deleteGerante,
  getGerantes,
  updateGerante,
} from './personnel.api'
import type { Gerante, GeranteForm, LaravelPaginated } from './personnel.types'

const emptyForm: GeranteForm = {
  name: '',
  email: '',
  password: '',
  actif: true,
}

function formatDate(value?: string) {
  return value ? new Date(value).toLocaleDateString('fr-FR') : '-'
}

function GerantesPage() {
  const [items, setItems] = useState<LaravelPaginated<Gerante> | null>(null)
  const [form, setForm] = useState<GeranteForm>(emptyForm)
  const [editing, setEditing] = useState<Gerante | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [error, setError] = useState<string | null>(null)
  const filtersReady = useRef(false)

  const gerantes = useMemo(() => items?.data ?? [], [items])
  const activeCount = useMemo(() => gerantes.filter((gerante) => gerante.actif).length, [gerantes])

  const loadPage = useCallback(async (nextPage: number, nextSearch: string, nextStatus: string) => {
    setLoading(true)
    setError(null)
    try {
      setItems(
        await getGerantes({
          page: nextPage,
          search: nextSearch || undefined,
          actif: nextStatus === 'all' ? undefined : nextStatus === 'active',
        }),
      )
      setPage(nextPage)
    } catch {
      setError('Impossible de charger les gerantes.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    getGerantes({ page: 1 })
      .then((response) => {
        setItems(response)
        setPage(1)
      })
      .catch(() => setError('Impossible de charger les gerantes.'))
      .finally(() => setLoading(false))
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

  const resetForm = () => {
    setForm(emptyForm)
    setEditing(null)
    setModalOpen(false)
  }

  const openModal = (gerante?: Gerante) => {
    if (gerante) {
      setEditing(gerante)
      setForm({
        name: gerante.name,
        email: gerante.email,
        password: '',
        actif: gerante.actif,
      })
    } else {
      setEditing(null)
      setForm(emptyForm)
    }

    setModalOpen(true)
  }

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setSaving(true)
    setError(null)
    try {
      if (!editing && form.password.trim().length < 8) {
        setError('Le mot de passe doit contenir au moins 8 caracteres.')
        return
      }

      if (editing) {
        await updateGerante(editing.id, form)
      } else {
        await createGerante(form)
      }
      resetForm()
      await loadPage(1, search, statusFilter)
    } catch {
      setError('Enregistrement impossible. Verifiez l email et le mot de passe.')
    } finally {
      setSaving(false)
    }
  }

  const toggleActive = async (gerante: Gerante) => {
    try {
      await updateGerante(gerante.id, {
        name: gerante.name,
        email: gerante.email,
        password: '',
        actif: !gerante.actif,
      })
      await loadPage(page, search, statusFilter)
    } catch {
      setError('Changement de statut impossible.')
    }
  }

  const remove = async (gerante: Gerante) => {
    if (!window.confirm(`Supprimer la gerante "${gerante.name}" ?`)) {
      return
    }

    try {
      await deleteGerante(gerante.id)
      await loadPage(page, search, statusFilter)
    } catch {
      setError('Suppression impossible pour cette gerante.')
    }
  }

  return (
    <PersonnelLayout
      title="Gerantes"
      subtitle="Creez, activez et securisez les comptes responsables du salon."
      action={
        <button type="button" onClick={() => openModal()} className={`${primaryButtonClass} inline-flex w-full items-center justify-center gap-2 sm:w-auto`}>
          <Plus className="h-4 w-4" />
          Nouvelle gerante
        </button>
      }
    >
      <section className="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="relative w-full lg:max-w-md">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
              placeholder="Rechercher une gerante..."
            />
          </div>
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <select
              value={statusFilter}
              onChange={(event) => setStatusFilter(event.target.value)}
              className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-bold text-gray-700 sm:w-auto"
            >
              <option value="all">Tous les statuts</option>
              <option value="active">Actives</option>
              <option value="inactive">Inactives</option>
            </select>
            <button type="button" onClick={() => void loadPage(page, search, statusFilter)} className={`${secondaryButtonClass} justify-center`} title="Actualiser">
              <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            </button>
          </div>
        </div>
        <div className="mt-4 grid gap-3 text-sm font-bold text-gray-500 sm:grid-cols-3">
          <span>{items?.total ?? 0} gerante(s)</span>
          <span>{activeCount} active(s) sur cette page</span>
          <span>Acces admin interne</span>
        </div>
      </section>

      {error && <ErrorState label={error} />}

      <section className="grid gap-3 lg:hidden">
        {loading ? (
          Array.from({ length: 4 }).map((_, index) => (
            <article key={index} className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
              <div className="h-5 w-2/3 animate-pulse rounded bg-gray-100" />
              <div className="mt-3 h-4 w-full animate-pulse rounded bg-gray-100" />
              <div className="mt-3 h-4 w-1/2 animate-pulse rounded bg-gray-100" />
            </article>
          ))
        ) : gerantes.length === 0 ? (
          <EmptyState label="Aucune gerante trouvee." />
        ) : (
          gerantes.map((gerante) => (
            <article key={gerante.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.55)]">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <h2 className="truncate text-base font-black text-gray-950">{gerante.name}</h2>
                  <p className="mt-1 flex items-center gap-2 truncate text-sm font-semibold text-gray-500">
                    <Mail className="h-4 w-4 shrink-0 text-[#e91e63]" />
                    {gerante.email}
                  </p>
                </div>
                <StatusBadge active={gerante.actif} />
              </div>
              <div className="mt-4 flex items-center justify-between gap-3">
                <span className="inline-flex items-center gap-2 text-sm font-black text-[#c41468]">
                  <ShieldCheck className="h-4 w-4" />
                  Role gerante
                </span>
                <div className="flex justify-end gap-1">
                  <button type="button" onClick={() => void toggleActive(gerante)} className="rounded-lg p-2 text-gray-600 transition hover:bg-gray-100" title={gerante.actif ? 'Desactiver' : 'Activer'}>
                    {gerante.actif ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                  <button type="button" onClick={() => openModal(gerante)} className="rounded-lg p-2 text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                    <Edit className="h-4 w-4" />
                  </button>
                  <button type="button" onClick={() => void remove(gerante)} className="rounded-lg p-2 text-red-600 transition hover:bg-red-50" title="Supprimer">
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </article>
          ))
        )}
      </section>

      <section className="hidden overflow-hidden rounded-xl border border-gray-100 bg-white shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)] lg:block">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[760px] text-left text-sm">
            <thead className="bg-gray-50 text-xs font-black uppercase tracking-[0.12em] text-gray-500">
              <tr>
                <th className="px-5 py-3">Nom</th>
                <th className="px-5 py-3">Email</th>
                <th className="px-5 py-3">Statut</th>
                <th className="px-5 py-3">Creation</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                Array.from({ length: 5 }).map((_, index) => (
                  <tr key={index}>
                    {Array.from({ length: 5 }).map((__, cell) => (
                      <td key={cell} className="px-5 py-4">
                        <div className="h-5 animate-pulse rounded bg-gray-100" />
                      </td>
                    ))}
                  </tr>
                ))
              ) : gerantes.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-8">
                    <EmptyState label="Aucune gerante trouvee." />
                  </td>
                </tr>
              ) : (
                gerantes.map((gerante) => (
                  <tr key={gerante.id} className="transition hover:bg-[#fff8fb]">
                    <td className="px-5 py-4">
                      <div className="font-black text-gray-950">{gerante.name}</div>
                      <div className="text-xs font-bold text-gray-400">#{gerante.id}</div>
                    </td>
                    <td className="px-5 py-4 font-semibold text-gray-500">{gerante.email}</td>
                    <td className="px-5 py-4"><StatusBadge active={gerante.actif} /></td>
                    <td className="px-5 py-4 font-semibold text-gray-500">{formatDate(gerante.created_at)}</td>
                    <td className="px-5 py-4">
                      <div className="flex justify-end gap-1">
                        <button type="button" onClick={() => void toggleActive(gerante)} className="rounded-lg p-2 text-gray-600 transition hover:bg-gray-100" title={gerante.actif ? 'Desactiver' : 'Activer'}>
                          {gerante.actif ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                        <button type="button" onClick={() => openModal(gerante)} className="rounded-lg p-2 text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                          <Edit className="h-4 w-4" />
                        </button>
                        <button type="button" onClick={() => void remove(gerante)} className="rounded-lg p-2 text-red-600 transition hover:bg-red-50" title="Supprimer">
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

      {modalOpen && (
        <Modal title={editing ? 'Modifier gerante' : 'Nouvelle gerante'} onClose={resetForm}>
          <form onSubmit={submit} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField label="Nom complet">
                <input
                  className={inputClass}
                  value={form.name}
                  onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
                  required
                  placeholder="Ex: Awa Ndiaye"
                />
              </FormField>
              <FormField label="Email">
                <input
                  className={inputClass}
                  type="email"
                  value={form.email}
                  onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))}
                  required
                  placeholder="awa@example.com"
                />
              </FormField>
              <FormField label={editing ? 'Nouveau mot de passe' : 'Mot de passe'}>
                <input
                  className={inputClass}
                  type="password"
                  minLength={editing ? undefined : 8}
                  value={form.password}
                  onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))}
                  required={!editing}
                  placeholder={editing ? 'Laisser vide pour conserver' : 'Minimum 8 caracteres'}
                />
              </FormField>
              <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold sm:self-end">
                <input
                  type="checkbox"
                  checked={form.actif}
                  onChange={(event) => setForm((current) => ({ ...current, actif: event.target.checked }))}
                />
                Compte actif
              </label>
            </div>
            <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
              <button type="button" onClick={resetForm} className={secondaryButtonClass}>
                Annuler
              </button>
              <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Enregistrement...' : editing ? 'Modifier' : 'Creer'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </PersonnelLayout>
  )
}

export default GerantesPage
