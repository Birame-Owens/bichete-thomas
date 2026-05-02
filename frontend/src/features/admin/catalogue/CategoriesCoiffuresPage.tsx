import { useEffect, useMemo, useState, type FormEvent } from 'react'
import {
  ArrowUpDown,
  Edit,
  Eye,
  EyeOff,
  Image,
  Package,
  Plus,
  RefreshCw,
  Search,
  Trash2,
  X,
} from 'lucide-react'
import CatalogueLayout from './components/CatalogueLayout'
import { EmptyState, ErrorState, FormField, Modal, Pagination } from './components/CatalogueUi'
import {
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from './components/catalogueUiTokens'
import {
  createCategorieCoiffure,
  deleteCategorieCoiffure,
  getCategoriesCoiffures,
  updateCategorieCoiffure,
} from './catalogue.api'
import type { CategorieCoiffure, CategorieForm, LaravelPaginated } from './catalogue.types'

const emptyForm: CategorieForm = {
  nom: '',
  description: '',
  actif: true,
  image: null,
}

function CategoriesCoiffuresPage() {
  const [items, setItems] = useState<LaravelPaginated<CategorieCoiffure> | null>(null)
  const [form, setForm] = useState<CategorieForm>(emptyForm)
  const [editing, setEditing] = useState<CategorieCoiffure | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const categories = useMemo(() => items?.data ?? [], [items])

  const loadPage = async (nextPage: number, nextSearch = search, nextStatus = statusFilter) => {
    setLoading(true)
    setError(null)
    try {
      setItems(
        await getCategoriesCoiffures({
          page: nextPage,
          search: nextSearch || undefined,
          actif: nextStatus === 'all' ? undefined : nextStatus === 'active',
        }),
      )
      setPage(nextPage)
    } catch {
      setError('Impossible de charger les categories.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    getCategoriesCoiffures({ page: 1 })
      .then((response) => {
        setItems(response)
        setPage(1)
      })
      .catch(() => setError('Impossible de charger les categories.'))
      .finally(() => setLoading(false))
  }, [])

  const resetForm = () => {
    setForm(emptyForm)
    setEditing(null)
    setImagePreview(null)
    setModalOpen(false)
  }

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setSaving(true)
    setError(null)
    try {
      if (editing) {
        await updateCategorieCoiffure(editing.id, form)
      } else {
        await createCategorieCoiffure(form)
      }
      resetForm()
      await loadPage(1)
    } catch {
      setError('Enregistrement impossible. Verifiez les champs.')
    } finally {
      setSaving(false)
    }
  }

  const openModal = (category?: CategorieCoiffure) => {
    if (category) {
      setEditing(category)
      setForm({
        nom: category.nom,
        description: category.description ?? '',
        actif: category.actif,
        image: null,
      })
      setImagePreview(category.image)
    } else {
      setEditing(null)
      setForm(emptyForm)
      setImagePreview(null)
    }
    setModalOpen(true)
  }

  const handleImage = (file: File | null) => {
    setForm((current) => ({ ...current, image: file }))
    setImagePreview(file ? URL.createObjectURL(file) : editing?.image ?? null)
  }

  const toggleActive = async (category: CategorieCoiffure) => {
    try {
      await updateCategorieCoiffure(category.id, {
        nom: category.nom,
        description: category.description ?? '',
        actif: !category.actif,
        image: null,
      })
      await loadPage(page)
    } catch {
      setError('Changement de statut impossible.')
    }
  }

  const remove = async (category: CategorieCoiffure) => {
    if (!window.confirm(`Supprimer la categorie "${category.nom}" ?`)) {
      return
    }

    try {
      await deleteCategorieCoiffure(category.id)
      await loadPage(page)
    } catch {
      setError('Suppression impossible. Cette categorie contient peut-etre deja des coiffures.')
    }
  }

  const submitFilters = (nextSearch = search, nextStatus = statusFilter) => {
    void loadPage(1, nextSearch, nextStatus)
  }

  return (
    <CatalogueLayout
      title="Categories coiffures"
      subtitle="Gerez les familles du catalogue avec photo, statut et nombre de coiffures."
      action={
        <button type="button" onClick={() => openModal()} className={`${primaryButtonClass} inline-flex items-center gap-2`}>
          <Plus className="h-4 w-4" />
          Nouvelle categorie
        </button>
      }
    >
      <section className="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="relative w-full lg:max-w-md">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value)
                submitFilters(event.target.value)
              }}
              className="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
              placeholder="Rechercher une categorie..."
            />
          </div>
          <div className="flex items-center gap-2">
            <select
              value={statusFilter}
              onChange={(event) => {
                setStatusFilter(event.target.value)
                submitFilters(search, event.target.value)
              }}
              className="rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-bold text-gray-700"
            >
              <option value="all">Tous les statuts</option>
              <option value="active">Actives</option>
              <option value="inactive">Inactives</option>
            </select>
            <button type="button" onClick={() => void loadPage(page)} className={secondaryButtonClass} title="Actualiser">
              <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            </button>
          </div>
        </div>
        <p className="mt-4 text-sm font-bold text-gray-500">{items?.total ?? 0} categorie(s)</p>
      </section>

      {error && <ErrorState label={error} />}

      <section className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[840px] text-left text-sm">
            <thead className="bg-gray-50 text-xs font-black uppercase tracking-[0.12em] text-gray-500">
              <tr>
                <th className="px-5 py-3">
                  <span className="inline-flex items-center gap-2">
                    Nom <ArrowUpDown className="h-3 w-3" />
                  </span>
                </th>
                <th className="px-5 py-3">Image</th>
                <th className="px-5 py-3">Description</th>
                <th className="px-5 py-3">Coiffures</th>
                <th className="px-5 py-3">Statut</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                Array.from({ length: 5 }).map((_, index) => (
                  <tr key={index}>
                    {Array.from({ length: 6 }).map((__, cell) => (
                      <td key={cell} className="px-5 py-4">
                        <div className="h-5 animate-pulse rounded bg-gray-100" />
                      </td>
                    ))}
                  </tr>
                ))
              ) : categories.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-5 py-8">
                    <EmptyState label="Aucune categorie trouvee." />
                  </td>
                </tr>
              ) : (
                categories.map((category) => (
                  <tr key={category.id} className="transition hover:bg-[#fff8fb]">
                    <td className="px-5 py-4">
                      <div className="font-black text-gray-950">{category.nom}</div>
                      <div className="text-xs font-bold text-gray-400">#{category.id}</div>
                    </td>
                    <td className="px-5 py-4">
                      {category.image ? (
                        <img src={category.image} alt={category.nom} className="h-12 w-12 rounded-lg object-cover" />
                      ) : (
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100">
                          <Image className="h-5 w-5 text-gray-400" />
                        </div>
                      )}
                    </td>
                    <td className="max-w-xs px-5 py-4 font-medium text-gray-500">
                      {category.description ? (
                        <span className="line-clamp-2">{category.description}</span>
                      ) : (
                        <span className="italic text-gray-400">Aucune description</span>
                      )}
                    </td>
                    <td className="px-5 py-4">
                      <span className="inline-flex items-center gap-2 font-black text-[#c41468]">
                        <Package className="h-4 w-4" />
                        {category.coiffures_count ?? 0}
                      </span>
                    </td>
                    <td className="px-5 py-4">
                      <span
                        className={`rounded-full px-3 py-1 text-xs font-black ${
                          category.actif ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'
                        }`}
                      >
                        {category.actif ? 'Actif' : 'Inactif'}
                      </span>
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex justify-end gap-1">
                        <button
                          type="button"
                          onClick={() => void toggleActive(category)}
                          className="rounded-lg p-2 text-gray-600 transition hover:bg-gray-100"
                          title={category.actif ? 'Desactiver' : 'Activer'}
                        >
                          {category.actif ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                        <button
                          type="button"
                          onClick={() => openModal(category)}
                          className="rounded-lg p-2 text-indigo-600 transition hover:bg-indigo-50"
                          title="Modifier"
                        >
                          <Edit className="h-4 w-4" />
                        </button>
                        {(category.coiffures_count ?? 0) === 0 && (
                          <button
                            type="button"
                            onClick={() => void remove(category)}
                            className="rounded-lg p-2 text-red-600 transition hover:bg-red-50"
                            title="Supprimer"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        )}
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
          onPrevious={() => void loadPage(page - 1)}
          onNext={() => void loadPage(page + 1)}
        />
      )}

      {modalOpen && (
        <Modal title={editing ? 'Modifier la categorie' : 'Nouvelle categorie'} onClose={resetForm}>
          <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
              <section className="space-y-4 rounded-xl bg-gray-50 p-4">
                <FormField label="Nom de la categorie">
                  <input
                    className={inputClass}
                    value={form.nom}
                    onChange={(event) => setForm((current) => ({ ...current, nom: event.target.value }))}
                    required
                    placeholder="Ex: Tresses"
                  />
                </FormField>
                <FormField label="Description">
                  <textarea
                    className={inputClass}
                    rows={6}
                    value={form.description}
                    onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))}
                    placeholder="Description de la categorie..."
                  />
                </FormField>
                <label className="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 text-sm font-bold">
                  <input
                    type="checkbox"
                    checked={form.actif}
                    onChange={(event) => setForm((current) => ({ ...current, actif: event.target.checked }))}
                  />
                  Categorie active
                </label>
              </section>

              <section className="rounded-xl bg-gray-50 p-4">
                <h3 className="mb-3 text-lg font-black text-gray-900">Image</h3>
                <div className="flex h-44 items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-white">
                  {imagePreview ? (
                    <div className="relative h-full w-full">
                      <img src={imagePreview} alt="Apercu" className="h-full w-full object-cover" />
                      <button
                        type="button"
                        onClick={() => handleImage(null)}
                        className="absolute right-2 top-2 rounded-full bg-red-500 p-1 text-white"
                      >
                        <X className="h-4 w-4" />
                      </button>
                    </div>
                  ) : (
                    <Image className="h-12 w-12 text-gray-300" />
                  )}
                </div>
                <label className="mt-3 flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-black text-[#e91e63] hover:bg-[#fff2f7]">
                  <Image className="h-4 w-4" />
                  Choisir une image
                  <input type="file" accept="image/*" className="sr-only" onChange={(event) => handleImage(event.target.files?.[0] ?? null)} />
                </label>
              </section>
            </div>

            <div className="flex justify-end gap-3 border-t border-gray-100 pt-5">
              <button type="button" onClick={resetForm} className={secondaryButtonClass}>
                Annuler
              </button>
              <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Sauvegarde...' : editing ? 'Modifier' : 'Creer'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </CatalogueLayout>
  )
}

export default CategoriesCoiffuresPage
