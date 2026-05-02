import { useEffect, useMemo, useState, type FormEvent } from 'react'
import {
  CheckCircle,
  Edit,
  Eye,
  EyeOff,
  Filter,
  Image,
  Plus,
  RefreshCw,
  Search,
  Tag,
  Trash2,
  Upload,
  X,
} from 'lucide-react'
import CatalogueLayout from './components/CatalogueLayout'
import { EmptyState, ErrorState, FormField, Modal, Pagination } from './components/CatalogueUi'
import {
  dangerButtonClass,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from './components/catalogueUiTokens'
import {
  createCoiffure,
  deleteCoiffure,
  getCategoriesCoiffures,
  getCoiffures,
  getOptionsCoiffures,
  updateCoiffure,
} from './catalogue.api'
import type {
  CategorieCoiffure,
  Coiffure,
  CoiffureForm,
  LaravelPaginated,
  OptionCoiffure,
} from './catalogue.types'

const emptyForm: CoiffureForm = {
  categorie_coiffure_id: '',
  nom: '',
  description: '',
  actif: true,
  option_ids: [],
  images: [],
  variantes: [{ nom: '', prix: '', duree_minutes: '', actif: true }],
}

function formatMoney(value: number | string) {
  return `${Number(value).toLocaleString('fr-FR')} FCFA`
}

function firstPrice(coiffure: Coiffure) {
  const variante = coiffure.variantes?.[0]
  return variante ? formatMoney(variante.prix) : 'Prix non renseigne'
}

function CoiffuresPage() {
  const [items, setItems] = useState<LaravelPaginated<Coiffure> | null>(null)
  const [categories, setCategories] = useState<CategorieCoiffure[]>([])
  const [options, setOptions] = useState<OptionCoiffure[]>([])
  const [form, setForm] = useState<CoiffureForm>(emptyForm)
  const [editing, setEditing] = useState<Coiffure | null>(null)
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [categoryFilter, setCategoryFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [search, setSearch] = useState('')
  const [imagePreviews, setImagePreviews] = useState<string[]>([])
  const [error, setError] = useState<string | null>(null)

  const coiffures = useMemo(() => items?.data ?? [], [items])

  const loadPage = async (nextPage: number, nextSearch = search, nextCategory = categoryFilter, nextStatus = statusFilter) => {
    setLoading(true)
    setError(null)
    try {
      const [coiffuresResponse, categoriesResponse, optionsResponse] = await Promise.all([
        getCoiffures({
          page: nextPage,
          search: nextSearch || undefined,
          categorie_coiffure_id: nextCategory || undefined,
          actif: nextStatus === 'all' ? undefined : nextStatus === 'active',
        }),
        getCategoriesCoiffures(),
        getOptionsCoiffures(),
      ])
      setItems(coiffuresResponse)
      setCategories(categoriesResponse.data)
      setOptions(optionsResponse.data)
      setPage(nextPage)
    } catch {
      setError('Impossible de charger les coiffures.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    Promise.all([getCoiffures({ page: 1 }), getCategoriesCoiffures(), getOptionsCoiffures()])
      .then(([coiffuresResponse, categoriesResponse, optionsResponse]) => {
        setItems(coiffuresResponse)
        setCategories(categoriesResponse.data)
        setOptions(optionsResponse.data)
        setPage(1)
      })
      .catch(() => setError('Impossible de charger les coiffures.'))
      .finally(() => setLoading(false))
  }, [])

  const resetForm = () => {
    setForm(emptyForm)
    setEditing(null)
    setImagePreviews([])
    setModalOpen(false)
  }

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setSaving(true)
    setError(null)
    try {
      if (editing) {
        await updateCoiffure(editing.id, form)
      } else {
        await createCoiffure(form)
      }
      resetForm()
      await loadPage(1)
    } catch {
      setError('Enregistrement impossible. Verifiez la categorie, les photos et les variantes.')
    } finally {
      setSaving(false)
    }
  }

  const openModal = (coiffure?: Coiffure) => {
    if (coiffure) {
      setEditing(coiffure)
      setForm({
        categorie_coiffure_id: String(coiffure.categorie_coiffure_id),
        nom: coiffure.nom,
        description: coiffure.description ?? '',
        actif: coiffure.actif,
        option_ids: coiffure.options?.map((option) => option.id) ?? [],
        images: [],
        variantes:
          coiffure.variantes && coiffure.variantes.length > 0
            ? coiffure.variantes.map((variante) => ({
                nom: variante.nom,
                prix: String(variante.prix),
                duree_minutes: String(variante.duree_minutes),
                actif: variante.actif,
              }))
            : [{ nom: '', prix: '', duree_minutes: '', actif: true }],
      })
      setImagePreviews([coiffure.image, ...(coiffure.images ?? []).map((image) => image.url)].filter(Boolean) as string[])
    } else {
      setEditing(null)
      setForm(emptyForm)
      setImagePreviews([])
    }

    setModalOpen(true)
  }

  const handleImages = (files: FileList | null) => {
    const selected = Array.from(files ?? []).slice(0, 4)
    setForm((current) => ({ ...current, images: selected }))
    setImagePreviews(selected.map((file) => URL.createObjectURL(file)))
  }

  const toggleOption = (id: number) => {
    setForm((current) => ({
      ...current,
      option_ids: current.option_ids.includes(id)
        ? current.option_ids.filter((optionId) => optionId !== id)
        : [...current.option_ids, id],
    }))
  }

  const toggleActive = async (coiffure: Coiffure) => {
    try {
      await updateCoiffure(coiffure.id, {
        categorie_coiffure_id: String(coiffure.categorie_coiffure_id),
        nom: coiffure.nom,
        description: coiffure.description ?? '',
        actif: !coiffure.actif,
        option_ids: coiffure.options?.map((option) => option.id) ?? [],
        images: [],
        variantes:
          coiffure.variantes?.map((variante) => ({
            nom: variante.nom,
            prix: String(variante.prix),
            duree_minutes: String(variante.duree_minutes),
            actif: variante.actif,
          })) ?? [],
      })
      await loadPage(page)
    } catch {
      setError('Changement de statut impossible.')
    }
  }

  const remove = async (coiffure: Coiffure) => {
    if (!window.confirm(`Supprimer la coiffure "${coiffure.nom}" ?`)) {
      return
    }

    try {
      await deleteCoiffure(coiffure.id)
      await loadPage(page)
    } catch {
      setError('Suppression impossible.')
    }
  }

  const submitFilters = (nextSearch = search, nextCategory = categoryFilter, nextStatus = statusFilter) => {
    void loadPage(1, nextSearch, nextCategory, nextStatus)
  }

  return (
    <CatalogueLayout
      title="Coiffures"
      subtitle="Gerez les prestations, photos, prix et variantes du salon."
      action={
        <button type="button" onClick={() => openModal()} className={`${primaryButtonClass} inline-flex items-center gap-2`}>
          <Plus className="h-4 w-4" />
          Ajouter coiffure
        </button>
      }
    >
      <section className="mb-5 rounded-xl border border-gray-100 bg-white p-4 shadow-[0_18px_36px_-32px_rgba(20,20,43,0.5)]">
        <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
          <div className="relative w-full xl:max-w-md">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value)
                submitFilters(event.target.value)
              }}
              className="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm font-semibold outline-none focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10"
              placeholder="Rechercher une coiffure..."
            />
          </div>
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <select
              value={categoryFilter}
              onChange={(event) => {
                setCategoryFilter(event.target.value)
                submitFilters(search, event.target.value)
              }}
              className="rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-bold text-gray-700"
            >
              <option value="">Toutes les categories</option>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.nom}
                </option>
              ))}
            </select>
            <select
              value={statusFilter}
              onChange={(event) => {
                setStatusFilter(event.target.value)
                submitFilters(search, categoryFilter, event.target.value)
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
        <div className="mt-4 flex items-center gap-2 text-sm font-bold text-gray-500">
          <Filter className="h-4 w-4 text-[#e91e63]" />
          {items?.total ?? 0} coiffure(s)
        </div>
      </section>

      {error && <ErrorState label={error} />}

      {loading ? (
        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: 6 }).map((_, index) => (
            <div key={index} className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
              <div className="h-52 animate-pulse bg-gray-100" />
              <div className="space-y-3 p-4">
                <div className="h-4 w-2/3 animate-pulse rounded bg-gray-100" />
                <div className="h-3 w-full animate-pulse rounded bg-gray-100" />
                <div className="h-3 w-1/2 animate-pulse rounded bg-gray-100" />
              </div>
            </div>
          ))}
        </div>
      ) : coiffures.length === 0 ? (
        <EmptyState label="Aucune coiffure ne correspond aux filtres." />
      ) : (
        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
          {coiffures.map((coiffure) => (
            <article
              key={coiffure.id}
              className="group overflow-hidden rounded-xl border border-gray-100 bg-white shadow-[0_18px_36px_-32px_rgba(20,20,43,0.55)] transition hover:-translate-y-0.5 hover:shadow-[0_22px_42px_-30px_rgba(20,20,43,0.7)]"
            >
              <div className="relative h-52 overflow-hidden bg-[#fff2f7]">
                {coiffure.image ? (
                  <img
                    src={coiffure.image}
                    alt={coiffure.nom}
                    className="h-full w-full object-cover object-center transition duration-300 group-hover:scale-105"
                  />
                ) : (
                  <div className="flex h-full items-center justify-center">
                    <Image className="h-12 w-12 text-[#e91e63]/45" />
                  </div>
                )}
                <div className="absolute left-3 top-3 flex flex-col gap-1">
                  <span className="rounded-full bg-[#e91e63] px-3 py-1 text-xs font-black text-white">
                    {coiffure.categorie?.nom ?? 'Sans categorie'}
                  </span>
                  {(coiffure.images?.length ?? 0) > 1 && (
                    <span className="rounded-full bg-white/90 px-3 py-1 text-xs font-black text-[#c41468]">
                      {coiffure.images?.length} photos
                    </span>
                  )}
                </div>
                <button
                  type="button"
                  onClick={() => void toggleActive(coiffure)}
                  className={`absolute right-3 top-3 rounded-full p-2 shadow-sm ${
                    coiffure.actif ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'
                  }`}
                  title={coiffure.actif ? 'Visible' : 'Masquee'}
                >
                  {coiffure.actif ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}
                </button>
              </div>

              <div className="p-4">
                <div className="mb-2 flex items-start justify-between gap-3">
                  <h3 className="line-clamp-1 text-lg font-black text-gray-950">{coiffure.nom}</h3>
                  <CheckCircle className={`mt-1 h-4 w-4 ${coiffure.actif ? 'text-emerald-500' : 'text-gray-300'}`} />
                </div>
                <p className="mb-3 line-clamp-2 min-h-10 text-sm font-medium text-gray-500">
                  {coiffure.description ?? 'Aucune description renseignee.'}
                </p>

                <div className="mb-3 flex items-center gap-2 text-sm font-black text-gray-900">
                  <Tag className="h-4 w-4 text-[#e91e63]" />
                  {firstPrice(coiffure)}
                </div>

                <div className="mb-3 flex flex-wrap gap-2">
                  {(coiffure.variantes ?? []).slice(0, 3).map((variante) => (
                    <span key={variante.id} className="rounded-full bg-[#fff2f7] px-2.5 py-1 text-xs font-black text-[#c41468]">
                      {variante.nom} · {variante.duree_minutes} min
                    </span>
                  ))}
                </div>

                <div className="mb-4 flex flex-wrap gap-2">
                  {(coiffure.options ?? []).slice(0, 3).map((option) => (
                    <span key={option.id} className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600">
                      {option.nom}
                    </span>
                  ))}
                </div>

                <div className="flex items-center justify-between border-t border-gray-100 pt-3">
                  <div className="flex gap-1">
                    <button
                      type="button"
                      onClick={() => openModal(coiffure)}
                      className="rounded-lg p-2 text-indigo-600 transition hover:bg-indigo-50"
                      title="Modifier"
                    >
                      <Edit className="h-4 w-4" />
                    </button>
                    <button
                      type="button"
                      onClick={() => void remove(coiffure)}
                      className="rounded-lg p-2 text-red-600 transition hover:bg-red-50"
                      title="Supprimer"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                  <span className="text-xs font-bold text-gray-400">
                    {coiffure.updated_at ? new Date(coiffure.updated_at).toLocaleDateString('fr-FR') : ''}
                  </span>
                </div>
              </div>
            </article>
          ))}
        </div>
      )}

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
        <Modal title={editing ? 'Modifier la coiffure' : 'Nouvelle coiffure'} onClose={resetForm}>
          <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-6 lg:grid-cols-2">
              <section className="rounded-xl bg-gray-50 p-4">
                <h3 className="mb-4 text-lg font-black text-gray-900">Informations de base</h3>
                <div className="space-y-4">
                  <FormField label="Nom">
                    <input
                      className={inputClass}
                      value={form.nom}
                      onChange={(event) => setForm((current) => ({ ...current, nom: event.target.value }))}
                      required
                      placeholder="Ex: Knotless Braids"
                    />
                  </FormField>
                  <FormField label="Categorie">
                    <select
                      className={inputClass}
                      value={form.categorie_coiffure_id}
                      onChange={(event) =>
                        setForm((current) => ({ ...current, categorie_coiffure_id: event.target.value }))
                      }
                      required
                    >
                      <option value="">Selectionnez une categorie</option>
                      {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                          {category.nom}
                        </option>
                      ))}
                    </select>
                  </FormField>
                  <FormField label="Description">
                    <textarea
                      className={inputClass}
                      rows={5}
                      value={form.description}
                      onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))}
                      placeholder="Description de la coiffure..."
                    />
                  </FormField>
                  <label className="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 text-sm font-bold">
                    <input
                      type="checkbox"
                      checked={form.actif}
                      onChange={(event) => setForm((current) => ({ ...current, actif: event.target.checked }))}
                    />
                    Coiffure visible dans le catalogue
                  </label>
                </div>
              </section>

              <section className="rounded-xl bg-gray-50 p-4">
                <h3 className="mb-4 text-lg font-black text-gray-900">Images</h3>
                <div className="rounded-xl border-2 border-dashed border-gray-300 bg-white p-5 text-center transition hover:border-[#e91e63]">
                  <Upload className="mx-auto mb-2 h-10 w-10 text-gray-400" />
                  <label className="cursor-pointer text-sm font-black text-[#e91e63]">
                    Charger 1 a 4 photos
                    <input type="file" accept="image/*" multiple onChange={(event) => handleImages(event.target.files)} className="sr-only" />
                  </label>
                  <p className="mt-1 text-xs font-bold text-gray-400">PNG, JPG, WEBP. La premiere photo est principale.</p>
                </div>
                {imagePreviews.length > 0 && (
                  <div className="mt-4 grid grid-cols-4 gap-2">
                    {imagePreviews.slice(0, 4).map((preview, index) => (
                      <div key={`${preview}-${index}`} className="relative">
                        <img src={preview} alt={`Apercu ${index + 1}`} className="h-20 w-full rounded-lg object-cover" />
                        {index === 0 && (
                          <span className="absolute bottom-1 left-1 rounded bg-[#e91e63] px-2 py-0.5 text-[10px] font-black text-white">
                            principale
                          </span>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </section>

              <section className="rounded-xl bg-gray-50 p-4 lg:col-span-2">
                <div className="mb-4 flex items-center justify-between gap-3">
                  <h3 className="text-lg font-black text-gray-900">Variantes et prix</h3>
                  <button
                    type="button"
                    onClick={() =>
                      setForm((current) => ({
                        ...current,
                        variantes: [...current.variantes, { nom: '', prix: '', duree_minutes: '', actif: true }],
                      }))
                    }
                    className={secondaryButtonClass}
                  >
                    Ajouter variante
                  </button>
                </div>
                <div className="space-y-3">
                  {form.variantes.map((variante, index) => (
                    <div key={index} className="grid gap-2 rounded-lg bg-white p-3 md:grid-cols-[1fr_140px_140px_auto]">
                      <input
                        className={inputClass}
                        placeholder="Nom variante"
                        value={variante.nom}
                        onChange={(event) =>
                          setForm((current) => ({
                            ...current,
                            variantes: current.variantes.map((item, itemIndex) =>
                              itemIndex === index ? { ...item, nom: event.target.value } : item,
                            ),
                          }))
                        }
                      />
                      <input
                        className={inputClass}
                        type="number"
                        min="0"
                        placeholder="Prix"
                        value={variante.prix}
                        onChange={(event) =>
                          setForm((current) => ({
                            ...current,
                            variantes: current.variantes.map((item, itemIndex) =>
                              itemIndex === index ? { ...item, prix: event.target.value } : item,
                            ),
                          }))
                        }
                      />
                      <input
                        className={inputClass}
                        type="number"
                        min="1"
                        placeholder="Duree"
                        value={variante.duree_minutes}
                        onChange={(event) =>
                          setForm((current) => ({
                            ...current,
                            variantes: current.variantes.map((item, itemIndex) =>
                              itemIndex === index ? { ...item, duree_minutes: event.target.value } : item,
                            ),
                          }))
                        }
                      />
                      <button
                        type="button"
                        onClick={() =>
                          setForm((current) => ({
                            ...current,
                            variantes: current.variantes.filter((_, itemIndex) => itemIndex !== index),
                          }))
                        }
                        className={dangerButtonClass}
                      >
                        <X className="h-4 w-4" />
                      </button>
                    </div>
                  ))}
                </div>
              </section>

              <section className="rounded-xl bg-gray-50 p-4 lg:col-span-2">
                <h3 className="mb-4 text-lg font-black text-gray-900">Options</h3>
                <div className="grid gap-2 md:grid-cols-2">
                  {options.length === 0 ? (
                    <p className="text-sm font-bold text-gray-400">Aucune option creee.</p>
                  ) : (
                    options.map((option) => (
                      <label key={option.id} className="flex items-center gap-3 rounded-lg bg-white px-3 py-3 text-sm font-bold text-gray-600">
                        <input
                          type="checkbox"
                          checked={form.option_ids.includes(option.id)}
                          onChange={() => toggleOption(option.id)}
                        />
                        {option.nom}
                      </label>
                    ))
                  )}
                </div>
              </section>
            </div>

            <div className="flex justify-end gap-3 border-t border-gray-100 pt-5">
              <button type="button" onClick={resetForm} className={secondaryButtonClass}>
                Annuler
              </button>
              <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center gap-2`}>
                {saving && <RefreshCw className="h-4 w-4 animate-spin" />}
                {saving ? 'Sauvegarde...' : editing ? 'Modifier la coiffure' : 'Creer la coiffure'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </CatalogueLayout>
  )
}

export default CoiffuresPage
