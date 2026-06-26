import { useEffect, useRef, useState } from 'react'
import { Plus, Trash2, Copy, Eye, EyeOff } from 'lucide-react'
import { EcommerceLayout } from './components/EcommerceLayout'
import {
  Panel,
  Modal,
  FormField,
  ErrorState,
  SuccessState,
  EmptyState,
  Pagination,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
  dangerButtonClass,
  money,
} from './components/EcommerceUi'
import { getProduits, getCategories, createProduit, updateProduit, deleteProduit, toggleProduitStatus, duplicateProduit } from './ecommerce.api'
import type { Produit, Categorie, LaravelPaginated, ProduitForm } from './ecommerce.types'

const emptyForm: ProduitForm = {
  nom: '',
  slug: '',
  description: '',
  description_courte: '',
  prix: '',
  prix_promo: '',
  categorie_id: '',
  stock_disponible: '',
  seuil_alerte: '',
  est_visible: true,
  est_populaire: false,
  est_nouveaute: false,
  image_principale: null,
  order_affichage: '0',
}

export function ProduitsPage() {
  const [items, setItems] = useState<LaravelPaginated<Produit> | null>(null)
  const [categories, setCategories] = useState<Categorie[]>([])
  const [form, setForm] = useState<ProduitForm>(emptyForm)
  const [editing, setEditing] = useState<Produit | null>(null)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const filtersReady = useRef(false)

  useEffect(() => {
    Promise.all([loadCategories(), loadProduits()])
  }, [])

  useEffect(() => {
    if (!filtersReady.current) {
      filtersReady.current = true
      return
    }
    const timer = setTimeout(() => {
      setPage(1)
      loadProduits()
    }, 300)
    return () => clearTimeout(timer)
  }, [search, categoryFilter, statusFilter])

  useEffect(() => {
    loadProduits()
  }, [page])

  async function loadCategories() {
    try {
      const data = await getCategories(1, 100)
      setCategories(data.data)
    } catch (err: any) {
      console.error('Erreur chargement catégories', err)
    }
  }

  async function loadProduits() {
    try {
      setLoading(true)
      setError(null)
      const data = await getProduits(page, 15, search, categoryFilter, statusFilter)
      setItems(data)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors du chargement des produits')
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
        await updateProduit(editing.id, form)
        setSuccess('Produit mis à jour')
      } else {
        await createProduit(form)
        setSuccess('Produit créé')
      }

      setModalOpen(false)
      setForm(emptyForm)
      setEditing(null)
      loadProduits()
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

  function openEditModal(produit: Produit) {
    setEditing(produit)
    setForm({
      nom: produit.nom,
      slug: produit.slug,
      description: produit.description,
      description_courte: produit.description_courte || '',
      prix: produit.prix.toString(),
      prix_promo: produit.prix_promo?.toString() || '',
      categorie_id: produit.categorie_id.toString(),
      stock_disponible: produit.stock_disponible.toString(),
      seuil_alerte: produit.seuil_alerte.toString(),
      est_visible: produit.est_visible,
      est_populaire: produit.est_populaire,
      est_nouveaute: produit.est_nouveaute,
      image_principale: null,
      order_affichage: produit.ordre_affichage.toString(),
    })
    setModalOpen(true)
  }

  async function handleDelete(id: number) {
    if (!window.confirm('Êtes-vous sûr de vouloir supprimer ce produit?')) return
    try {
      setError(null)
      await deleteProduit(id)
      setSuccess('Produit supprimé')
      loadProduits()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors de la suppression')
    }
  }

  async function handleToggleStatus(produit: Produit) {
    try {
      await toggleProduitStatus(produit.id)
      setSuccess(produit.est_visible ? 'Produit masqué' : 'Produit visible')
      loadProduits()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors du changement de statut')
    }
  }

  async function handleDuplicate(id: number) {
    try {
      await duplicateProduit(id)
      setSuccess('Produit dupliqué')
      loadProduits()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors de la duplication')
    }
  }

  const getCategoryName = (id: number) => categories.find(c => c.id === id)?.nom || '—'

  return (
    <EcommerceLayout>
      <Panel
        title="Gestion des produits"
        subtitle="Créer, modifier et gérer votre catalogue de produits"
        action={
          <button onClick={openCreateModal} className={`gap-2 ${primaryButtonClass}`}>
            <Plus size={18} />
            Ajouter un produit
          </button>
        }
      >
        {error && <ErrorState message={error} />}
        {success && (
          <SuccessState
            message={success}
            onDismiss={() => setSuccess(null)}
          />
        )}

        {/* Filtres */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 py-4">
          <input
            type="text"
            placeholder="Rechercher..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className={inputClass}
          />
          <select
            value={categoryFilter}
            onChange={e => setCategoryFilter(e.target.value)}
            className={inputClass}
          >
            <option value="">Toutes les catégories</option>
            {categories.map(cat => (
              <option key={cat.id} value={cat.id}>
                {cat.nom}
              </option>
            ))}
          </select>
          <select
            value={statusFilter}
            onChange={e => setStatusFilter(e.target.value)}
            className={inputClass}
          >
            <option value="">Tous les statuts</option>
            <option value="visible">Visible</option>
            <option value="hidden">Masqué</option>
          </select>
        </div>

        {/* Liste */}
        {loading ? (
          <div className="space-y-3">
            {[...Array(5)].map((_, i) => (
              <div key={i} className="h-20 bg-gray-200 rounded-lg animate-pulse" />
            ))}
          </div>
        ) : items?.data && items.data.length > 0 ? (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b border-gray-200 bg-gray-50">
                  <tr>
                    <th className="text-left px-4 py-3 font-semibold text-gray-700">Produit</th>
                    <th className="text-left px-4 py-3 font-semibold text-gray-700">Catégorie</th>
                    <th className="text-right px-4 py-3 font-semibold text-gray-700">Prix</th>
                    <th className="text-right px-4 py-3 font-semibold text-gray-700">Stock</th>
                    <th className="text-center px-4 py-3 font-semibold text-gray-700">Statut</th>
                    <th className="text-right px-4 py-3 font-semibold text-gray-700">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {items.data.map(produit => (
                    <tr key={produit.id} className="hover:bg-gray-50">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-3">
                          <img
                            src={produit.image_principale}
                            alt={produit.nom}
                            className="h-10 w-10 rounded object-cover"
                          />
                          <div>
                            <div className="font-medium text-gray-900">{produit.nom}</div>
                            <div className="text-xs text-gray-500">{produit.slug}</div>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-gray-700">{getCategoryName(produit.categorie_id)}</td>
                      <td className="px-4 py-3 text-right text-gray-900 font-medium">{money(produit.prix)}</td>
                      <td className="px-4 py-3 text-right text-gray-700">{produit.stock_disponible}</td>
                      <td className="px-4 py-3 text-center">
                        <span
                          className={`inline-block px-2.5 py-1 rounded-full text-xs font-medium ${
                            produit.est_visible
                              ? 'bg-green-100 text-green-700'
                              : 'bg-gray-100 text-gray-700'
                          }`}
                        >
                          {produit.est_visible ? 'Visible' : 'Masqué'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex justify-end gap-2">
                          <button
                            onClick={() => handleToggleStatus(produit)}
                            className="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors"
                            title={produit.est_visible ? 'Masquer' : 'Afficher'}
                          >
                            {produit.est_visible ? <Eye size={16} /> : <EyeOff size={16} />}
                          </button>
                          <button
                            onClick={() => openEditModal(produit)}
                            className="px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded transition-colors"
                          >
                            Éditer
                          </button>
                          <button
                            onClick={() => handleDuplicate(produit.id)}
                            className="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded transition-colors"
                            title="Dupliquer"
                          >
                            <Copy size={16} />
                          </button>
                          <button
                            onClick={() => handleDelete(produit.id)}
                            className="p-2 text-red-600 hover:text-red-900 hover:bg-red-100 rounded transition-colors"
                            title="Supprimer"
                          >
                            <Trash2 size={16} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
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
          <EmptyState title="Aucun produit" description="Créez votre premier produit pour commencer" />
        )}
      </Panel>

      {/* Modal Créer/Éditer */}
      <Modal isOpen={modalOpen} title={editing ? 'Modifier le produit' : 'Créer un produit'} onClose={() => setModalOpen(false)}>
        <form onSubmit={handleSubmit} className="space-y-4 p-6">
          <FormField label="Nom du produit">
            <input
              type="text"
              required
              value={form.nom}
              onChange={e => setForm({ ...form, nom: e.target.value })}
              className={inputClass}
            />
          </FormField>

          <FormField label="Slug (URL)">
            <input
              type="text"
              value={form.slug}
              onChange={e => setForm({ ...form, slug: e.target.value })}
              className={inputClass}
            />
          </FormField>

          <FormField label="Description courte">
            <textarea
              value={form.description_courte}
              onChange={e => setForm({ ...form, description_courte: e.target.value })}
              className={inputClass}
              rows={2}
            />
          </FormField>

          <FormField label="Description complète">
            <textarea
              value={form.description}
              onChange={e => setForm({ ...form, description: e.target.value })}
              className={inputClass}
              rows={4}
            />
          </FormField>

          <div className="grid grid-cols-2 gap-4">
            <FormField label="Prix (FCFA)">
              <input
                type="number"
                step="100"
                required
                value={form.prix}
                onChange={e => setForm({ ...form, prix: e.target.value })}
                className={inputClass}
              />
            </FormField>

            <FormField label="Prix promo (optionnel)">
              <input
                type="number"
                step="100"
                value={form.prix_promo}
                onChange={e => setForm({ ...form, prix_promo: e.target.value })}
                className={inputClass}
              />
            </FormField>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <FormField label="Catégorie">
              <select
                required
                value={form.categorie_id}
                onChange={e => setForm({ ...form, categorie_id: e.target.value })}
                className={inputClass}
              >
                <option value="">Sélectionner...</option>
                {categories.map(cat => (
                  <option key={cat.id} value={cat.id}>
                    {cat.nom}
                  </option>
                ))}
              </select>
            </FormField>

            <FormField label="Stock disponible">
              <input
                type="number"
                required
                value={form.stock_disponible}
                onChange={e => setForm({ ...form, stock_disponible: e.target.value })}
                className={inputClass}
              />
            </FormField>
          </div>

          <FormField label="Image principale">
            <input
              type="file"
              accept="image/*"
              onChange={e => setForm({ ...form, image_principale: e.target.files?.[0] || null })}
              className={inputClass}
            />
          </FormField>

          <div className="flex gap-2">
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={form.est_visible}
                onChange={e => setForm({ ...form, est_visible: e.target.checked })}
                className="w-4 h-4 rounded"
              />
              <span className="text-sm text-gray-700">Visible sur le site</span>
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={form.est_populaire}
                onChange={e => setForm({ ...form, est_populaire: e.target.checked })}
                className="w-4 h-4 rounded"
              />
              <span className="text-sm text-gray-700">Populaire</span>
            </label>
          </div>

          <div className="flex gap-3 border-t border-gray-200 pt-4 mt-6">
            <button type="submit" disabled={saving} className={`flex-1 ${primaryButtonClass}`}>
              {saving ? 'Enregistrement...' : 'Enregistrer'}
            </button>
            <button
              type="button"
              onClick={() => setModalOpen(false)}
              className={`flex-1 ${secondaryButtonClass}`}
            >
              Annuler
            </button>
          </div>
        </form>
      </Modal>
    </EcommerceLayout>
  )
}

function SuccessState({ message, onDismiss }: { message: string; onDismiss: () => void }) {
  useEffect(() => {
    const timer = setTimeout(onDismiss, 3000)
    return () => clearTimeout(timer)
  }, [])

  return (
    <div className="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 mb-4">
      ✓ {message}
    </div>
  )
}
