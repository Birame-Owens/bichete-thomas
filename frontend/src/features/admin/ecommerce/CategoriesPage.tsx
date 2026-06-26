import { useEffect, useRef, useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { EcommerceLayout } from './components/EcommerceLayout'
import {
  Panel,
  Modal,
  FormField,
  ErrorState,
  EmptyState,
  Pagination,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
} from './components/EcommerceUi'
import { getCategories, createCategory, updateCategory, deleteCategory } from './ecommerce.api'
import type { Categorie, LaravelPaginated, CategorieForm } from './ecommerce.types'

const emptyForm: CategorieForm = {
  nom: '',
  slug: '',
  description: '',
  image: null,
  ordre_affichage: '0',
  est_active: true,
  est_populaire: false,
}

export function CategoriesPage() {
  const [items, setItems] = useState<LaravelPaginated<Categorie> | null>(null)
  const [form, setForm] = useState<CategorieForm>(emptyForm)
  const [editing, setEditing] = useState<Categorie | null>(null)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const filtersReady = useRef(false)

  useEffect(() => {
    loadCategories()
  }, [])

  useEffect(() => {
    if (!filtersReady.current) {
      filtersReady.current = true
      return
    }
    const timer = setTimeout(() => {
      setPage(1)
      loadCategories()
    }, 300)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => {
    loadCategories()
  }, [page])

  async function loadCategories() {
    try {
      setLoading(true)
      setError(null)
      const data = await getCategories(page, 15, search)
      setItems(data)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors du chargement des catégories')
    } finally {
      setLoading(false)
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    try {
      setSaving(true)
      setError(null)

      if (editing) {
        await updateCategory(editing.id, form)
      } else {
        await createCategory(form)
      }

      setModalOpen(false)
      setForm(emptyForm)
      setEditing(null)
      loadCategories()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors de la sauvegarde')
    } finally {
      setSaving(false)
    }
  }

  function openCreateModal() {
    setForm(emptyForm)
    setEditing(null)
    setModalOpen(true)
  }

  function openEditModal(categorie: Categorie) {
    setEditing(categorie)
    setForm({
      nom: categorie.nom,
      slug: categorie.slug,
      description: categorie.description || '',
      image: null,
      ordre_affichage: categorie.ordre_affichage.toString(),
      est_active: categorie.est_active,
      est_populaire: categorie.est_populaire,
    })
    setModalOpen(true)
  }

  async function handleDelete(id: number) {
    if (!window.confirm('Êtes-vous sûr de vouloir supprimer cette catégorie?')) return
    try {
      setError(null)
      await deleteCategory(id)
      loadCategories()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors de la suppression')
    }
  }

  return (
    <EcommerceLayout>
      <Panel
        title="Gestion des catégories"
        subtitle="Organisez votre catalogue par catégories"
        action={
          <button onClick={openCreateModal} className={`gap-2 ${primaryButtonClass}`}>
            <Plus size={18} />
            Ajouter une catégorie
          </button>
        }
      >
        {error && <ErrorState message={error} />}

        {/* Recherche */}
        <div className="py-4">
          <input
            type="text"
            placeholder="Rechercher..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className={inputClass}
          />
        </div>

        {/* Liste */}
        {loading ? (
          <div className="space-y-3">
            {[...Array(5)].map((_, i) => (
              <div key={i} className="h-16 bg-gray-200 rounded-lg animate-pulse" />
            ))}
          </div>
        ) : items?.data && items.data.length > 0 ? (
          <>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {items.data.map(categorie => (
                <div key={categorie.id} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                  {categorie.image && (
                    <img
                      src={categorie.image}
                      alt={categorie.nom}
                      className="h-32 w-full rounded-lg object-cover mb-3"
                    />
                  )}
                  <h3 className="font-semibold text-gray-900">{categorie.nom}</h3>
                  <p className="text-sm text-gray-500 mt-1">{categorie.slug}</p>
                  <p className="text-sm text-gray-600 mt-2 line-clamp-2">{categorie.description}</p>
                  <div className="flex gap-2 mt-4">
                    <button
                      onClick={() => openEditModal(categorie)}
                      className="flex-1 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded transition-colors"
                    >
                      Éditer
                    </button>
                    <button
                      onClick={() => handleDelete(categorie.id)}
                      className="p-2 text-red-600 hover:bg-red-100 rounded transition-colors"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                </div>
              ))}
            </div>
            <Pagination
              currentPage={items.current_page}
              lastPage={items.last_page}
              onPageChange={setPage}
              total={items.total}
              perPage={items.per_page}
            />
          </>
        ) : (
          <EmptyState title="Aucune catégorie" description="Créez votre première catégorie" />
        )}
      </Panel>

      {/* Modal */}
      <Modal
        isOpen={modalOpen}
        title={editing ? 'Modifier la catégorie' : 'Créer une catégorie'}
        onClose={() => setModalOpen(false)}
      >
        <form onSubmit={handleSubmit} className="space-y-4 p-6">
          <FormField label="Nom">
            <input
              type="text"
              required
              value={form.nom}
              onChange={e => setForm({ ...form, nom: e.target.value })}
              className={inputClass}
            />
          </FormField>

          <FormField label="Slug">
            <input
              type="text"
              value={form.slug}
              onChange={e => setForm({ ...form, slug: e.target.value })}
              className={inputClass}
            />
          </FormField>

          <FormField label="Description">
            <textarea
              value={form.description}
              onChange={e => setForm({ ...form, description: e.target.value })}
              className={inputClass}
              rows={3}
            />
          </FormField>

          <FormField label="Image">
            <input
              type="file"
              accept="image/*"
              onChange={e => setForm({ ...form, image: e.target.files?.[0] || null })}
              className={inputClass}
            />
          </FormField>

          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              checked={form.est_active}
              onChange={e => setForm({ ...form, est_active: e.target.checked })}
              className="w-4 h-4 rounded"
            />
            <span className="text-sm text-gray-700">Actif</span>
          </label>

          <div className="flex gap-3 border-t border-gray-200 pt-4 mt-6">
            <button type="submit" disabled={saving} className={`flex-1 ${primaryButtonClass}`}>
              {saving ? 'Enregistrement...' : 'Enregistrer'}
            </button>
            <button type="button" onClick={() => setModalOpen(false)} className={`flex-1 ${secondaryButtonClass}`}>
              Annuler
            </button>
          </div>
        </form>
      </Modal>
    </EcommerceLayout>
  )
}
