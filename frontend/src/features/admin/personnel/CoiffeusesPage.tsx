import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { BadgePercent, Edit, Eye, EyeOff, Phone, Plus, RefreshCw, Search, Trash2 } from 'lucide-react'
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
  createCoiffeuse,
  deleteCoiffeuse,
  getCoiffeuses,
  updateCoiffeuse,
} from './personnel.api'
import type { Coiffeuse, CoiffeuseForm, LaravelPaginated } from './personnel.types'

const emptyForm: CoiffeuseForm = {
  nom: '',
  prenom: '',
  telephone: '',
  pourcentage_commission: '0',
  actif: true,
}

function fullName(coiffeuse: Coiffeuse) {
  return `${coiffeuse.prenom} ${coiffeuse.nom}`.trim()
}

function commission(value: number | string) {
  return `${Number(value).toLocaleString('fr-FR')}%`
}

function CoiffeusesPage() {
  const [items, setItems] = useState<LaravelPaginated<Coiffeuse> | null>(null)
  const [form, setForm] = useState<CoiffeuseForm>(emptyForm)
  const [editing, setEditing] = useState<Coiffeuse | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [error, setError] = useState<string | null>(null)
  const filtersReady = useRef(false)

  const coiffeuses = useMemo(() => items?.data ?? [], [items])
  const averageCommission = useMemo(() => {
    if (coiffeuses.length === 0) {
      return 0
    }

    return coiffeuses.reduce((sum, item) => sum + Number(item.pourcentage_commission), 0) / coiffeuses.length
  }, [coiffeuses])

  const loadPage = useCallback(async (nextPage: number, nextSearch: string, nextStatus: string) => {
    setLoading(true)
    setError(null)
    try {
      setItems(
        await getCoiffeuses({
          page: nextPage,
          search: nextSearch || undefined,
          actif: nextStatus === 'all' ? undefined : nextStatus === 'active',
        }),
      )
      setPage(nextPage)
    } catch {
      setError('Impossible de charger les coiffeuses.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    getCoiffeuses({ page: 1 })
      .then((response) => {
        setItems(response)
        setPage(1)
      })
      .catch(() => setError('Impossible de charger les coiffeuses.'))
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

  const openModal = (coiffeuse?: Coiffeuse) => {
    if (coiffeuse) {
      setEditing(coiffeuse)
      setForm({
        nom: coiffeuse.nom,
        prenom: coiffeuse.prenom,
        telephone: coiffeuse.telephone ?? '',
        pourcentage_commission: String(coiffeuse.pourcentage_commission),
        actif: coiffeuse.actif,
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
      if (editing) {
        await updateCoiffeuse(editing.id, form)
      } else {
        await createCoiffeuse(form)
      }
      resetForm()
      await loadPage(1, search, statusFilter)
    } catch {
      setError('Enregistrement impossible. Verifiez les champs.')
    } finally {
      setSaving(false)
    }
  }

  const toggleActive = async (coiffeuse: Coiffeuse) => {
    try {
      await updateCoiffeuse(coiffeuse.id, {
        nom: coiffeuse.nom,
        prenom: coiffeuse.prenom,
        telephone: coiffeuse.telephone ?? '',
        pourcentage_commission: String(coiffeuse.pourcentage_commission),
        actif: !coiffeuse.actif,
      })
      await loadPage(page, search, statusFilter)
    } catch {
      setError('Changement de statut impossible.')
    }
  }

  const remove = async (coiffeuse: Coiffeuse) => {
    if (!window.confirm(`Supprimer la coiffeuse "${fullName(coiffeuse)}" ?`)) {
      return
    }

    try {
      await deleteCoiffeuse(coiffeuse.id)
      await loadPage(page, search, statusFilter)
    } catch {
      setError('Suppression impossible pour cette coiffeuse.')
    }
  }

  return (
    <PersonnelLayout
      title="Coiffeuses"
      subtitle="Gerez les profils, commissions et disponibilites de l equipe."
      action={
        <button type="button" onClick={() => openModal()} className={`${primaryButtonClass} inline-flex w-full items-center justify-center gap-2 sm:w-auto`}>
          <Plus className="h-4 w-4" />
          Nouvelle coiffeuse
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
              placeholder="Rechercher une coiffeuse..."
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
          <span>{items?.total ?? 0} coiffeuse(s)</span>
          <span>Commission moyenne page: {commission(averageCommission)}</span>
          <span>{coiffeuses.filter((coiffeuse) => coiffeuse.actif).length} active(s) sur cette page</span>
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
        ) : coiffeuses.length === 0 ? (
          <EmptyState label="Aucune coiffeuse trouvee." />
        ) : (
          coiffeuses.map((coiffeuse) => (
            <article key={coiffeuse.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.55)]">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <h2 className="truncate text-base font-black text-gray-950">{fullName(coiffeuse)}</h2>
                  <p className="mt-1 flex items-center gap-2 truncate text-sm font-semibold text-gray-500">
                    <Phone className="h-4 w-4 shrink-0 text-[#e91e63]" />
                    {coiffeuse.telephone || 'Telephone non renseigne'}
                  </p>
                </div>
                <StatusBadge active={coiffeuse.actif} />
              </div>
              <div className="mt-4 flex items-center justify-between gap-3">
                <span className="inline-flex items-center gap-2 text-sm font-black text-[#c41468]">
                  <BadgePercent className="h-4 w-4" />
                  {commission(coiffeuse.pourcentage_commission)}
                </span>
                <div className="flex justify-end gap-1">
                  <button type="button" onClick={() => void toggleActive(coiffeuse)} className="rounded-lg p-2 text-gray-600 transition hover:bg-gray-100" title={coiffeuse.actif ? 'Desactiver' : 'Activer'}>
                    {coiffeuse.actif ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                  <button type="button" onClick={() => openModal(coiffeuse)} className="rounded-lg p-2 text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                    <Edit className="h-4 w-4" />
                  </button>
                  <button type="button" onClick={() => void remove(coiffeuse)} className="rounded-lg p-2 text-red-600 transition hover:bg-red-50" title="Supprimer">
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
          <table className="w-full min-w-[820px] text-left text-sm">
            <thead className="bg-gray-50 text-xs font-black uppercase tracking-[0.12em] text-gray-500">
              <tr>
                <th className="px-5 py-3">Coiffeuse</th>
                <th className="px-5 py-3">Telephone</th>
                <th className="px-5 py-3">Commission</th>
                <th className="px-5 py-3">Statut</th>
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
              ) : coiffeuses.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-5 py-8">
                    <EmptyState label="Aucune coiffeuse trouvee." />
                  </td>
                </tr>
              ) : (
                coiffeuses.map((coiffeuse) => (
                  <tr key={coiffeuse.id} className="transition hover:bg-[#fff8fb]">
                    <td className="px-5 py-4">
                      <div className="font-black text-gray-950">{fullName(coiffeuse)}</div>
                      <div className="text-xs font-bold text-gray-400">#{coiffeuse.id}</div>
                    </td>
                    <td className="px-5 py-4 font-semibold text-gray-500">{coiffeuse.telephone || '-'}</td>
                    <td className="px-5 py-4">
                      <span className="inline-flex items-center gap-2 font-black text-[#c41468]">
                        <BadgePercent className="h-4 w-4" />
                        {commission(coiffeuse.pourcentage_commission)}
                      </span>
                    </td>
                    <td className="px-5 py-4"><StatusBadge active={coiffeuse.actif} /></td>
                    <td className="px-5 py-4">
                      <div className="flex justify-end gap-1">
                        <button type="button" onClick={() => void toggleActive(coiffeuse)} className="rounded-lg p-2 text-gray-600 transition hover:bg-gray-100" title={coiffeuse.actif ? 'Desactiver' : 'Activer'}>
                          {coiffeuse.actif ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                        <button type="button" onClick={() => openModal(coiffeuse)} className="rounded-lg p-2 text-indigo-600 transition hover:bg-indigo-50" title="Modifier">
                          <Edit className="h-4 w-4" />
                        </button>
                        <button type="button" onClick={() => void remove(coiffeuse)} className="rounded-lg p-2 text-red-600 transition hover:bg-red-50" title="Supprimer">
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
        <Modal title={editing ? 'Modifier coiffeuse' : 'Nouvelle coiffeuse'} onClose={resetForm}>
          <form onSubmit={submit} className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField label="Prenom">
                <input
                  className={inputClass}
                  value={form.prenom}
                  onChange={(event) => setForm((current) => ({ ...current, prenom: event.target.value }))}
                  required
                  placeholder="Ex: Fatou"
                />
              </FormField>
              <FormField label="Nom">
                <input
                  className={inputClass}
                  value={form.nom}
                  onChange={(event) => setForm((current) => ({ ...current, nom: event.target.value }))}
                  required
                  placeholder="Ex: Diop"
                />
              </FormField>
              <FormField label="Telephone">
                <input
                  className={inputClass}
                  value={form.telephone}
                  onChange={(event) => setForm((current) => ({ ...current, telephone: event.target.value }))}
                  placeholder="+221 77 000 00 00"
                />
              </FormField>
              <FormField label="Commission %">
                <input
                  className={inputClass}
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  value={form.pourcentage_commission}
                  onChange={(event) => setForm((current) => ({ ...current, pourcentage_commission: event.target.value }))}
                  required
                />
              </FormField>
              <label className="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-3 text-sm font-bold sm:col-span-2">
                <input
                  type="checkbox"
                  checked={form.actif}
                  onChange={(event) => setForm((current) => ({ ...current, actif: event.target.checked }))}
                />
                Coiffeuse active
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

export default CoiffeusesPage
