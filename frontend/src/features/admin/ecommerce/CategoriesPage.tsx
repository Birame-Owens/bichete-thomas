import { useState, useEffect, useMemo, useRef } from 'react'
import {
  Plus, Search, Tag, Package, ChevronDown, ChevronRight,
  Edit2, Trash2, ToggleLeft, ToggleRight, X, Folder, FolderOpen,
  ImageIcon, AlertTriangle, RefreshCw, Star, Layers, CheckCircle2,
} from 'lucide-react'
import { EcommerceLayout } from './components/EcommerceLayout'
import {
  getCategoriesParents, getCategoryStats, toggleCategoryStatus,
  createCategory, updateCategory, deleteCategory,
} from './ecommerce.api'
import type { Categorie, SousCategorie, CategoryStats } from './ecommerce.types'

// ─── Types ────────────────────────────────────────────────────────────────────

type ModalMode =
  | { kind: 'create-category' }
  | { kind: 'create-subcategory'; parentId: number; parentNom: string }
  | { kind: 'edit-category'; category: Categorie }
  | { kind: 'edit-subcategory'; subcategory: SousCategorie; parentNom: string }

type DeleteTarget = { type: 'category' | 'subcategory'; id: number; nom: string }

interface ToastItem { id: number; message: string; type: 'success' | 'error' }

// ─── Helpers ──────────────────────────────────────────────────────────────────

function Skeleton({ className = '' }: { className?: string }) {
  return <div className={`bg-gray-100 rounded-lg animate-pulse ${className}`} />
}

function StatusBadge({ active }: { active: boolean }) {
  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold ${
      active ? 'bg-green-100 text-green-700' : 'bg-rose-100 text-rose-600'
    }`}>
      <span className={`w-1.5 h-1.5 rounded-full ${active ? 'bg-green-500' : 'bg-rose-400'}`} />
      {active ? 'Actif' : 'Masqué'}
    </span>
  )
}

// ─── Stat Card ────────────────────────────────────────────────────────────────

function StatCard({ title, value, icon: Icon, iconBg, iconColor, loading }: {
  title: string; value: number; icon: React.ElementType
  iconBg: string; iconColor: string; loading: boolean
}) {
  return (
    <div className="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
      <div className={`w-10 h-10 rounded-xl flex items-center justify-center mb-3 ${iconBg}`}>
        <Icon className={`w-5 h-5 ${iconColor}`} strokeWidth={1.5} />
      </div>
      {loading
        ? <div className="h-7 w-16 bg-gray-100 rounded-lg animate-pulse mb-1" />
        : <p className="text-2xl font-bold text-gray-900">{value}</p>
      }
      <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-widest mt-1">{title}</p>
    </div>
  )
}

// ─── Subcategory Row ──────────────────────────────────────────────────────────

function SubcatRow({ subcat, onEdit, onDelete, onToggle }: {
  subcat: SousCategorie
  onEdit: () => void
  onDelete: () => void
  onToggle: () => void
}) {
  return (
    <div className="flex items-center gap-3 px-4 py-3 bg-gray-50 rounded-xl border border-gray-200 hover:bg-gray-100 transition-colors group">
      {/* Image */}
      <div className="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
        {subcat.image
          ? <img src={subcat.image} alt={subcat.nom} className="w-full h-full object-cover" />
          : <Tag className="w-3.5 h-3.5 text-gray-400" strokeWidth={1.5} />
        }
      </div>

      {/* Info */}
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 truncate">{subcat.nom}</p>
        <p className="text-[11px] text-gray-500">{subcat.slug}</p>
      </div>

      {/* Produits */}
      <span className="text-xs text-gray-500 hidden sm:block">
        {subcat.produits_count} produit{subcat.produits_count !== 1 ? 's' : ''}
      </span>

      {/* Statut */}
      <StatusBadge active={subcat.est_active} />

      {/* Actions */}
      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
        <button
          onClick={onToggle}
          className="p-1.5 rounded-lg hover:bg-gray-200 transition-colors"
          title={subcat.est_active ? 'Désactiver' : 'Activer'}
        >
          {subcat.est_active
            ? <ToggleRight className="w-3.5 h-3.5 text-gray-600" strokeWidth={1.5} />
            : <ToggleLeft className="w-3.5 h-3.5 text-gray-500" strokeWidth={1.5} />
          }
        </button>
        <button onClick={onEdit} className="p-1.5 rounded-lg hover:bg-gray-200 transition-colors">
          <Edit2 className="w-3.5 h-3.5 text-gray-600" strokeWidth={1.5} />
        </button>
        <button onClick={onDelete} className="p-1.5 rounded-lg hover:bg-rose-50 transition-colors">
          <Trash2 className="w-3.5 h-3.5 text-rose-400" strokeWidth={1.5} />
        </button>
      </div>
    </div>
  )
}

// ─── Category Row ─────────────────────────────────────────────────────────────

function CategoryRow({
  category, expanded, onExpand, onEdit, onDelete, onToggle,
  onAddSubcategory, onEditSubcategory, onDeleteSubcategory, onToggleSubcategory,
}: {
  category: Categorie
  expanded: boolean
  onExpand: () => void
  onEdit: () => void
  onDelete: () => void
  onToggle: () => void
  onAddSubcategory: () => void
  onEditSubcategory: (s: SousCategorie) => void
  onDeleteSubcategory: (s: SousCategorie) => void
  onToggleSubcategory: (s: SousCategorie) => void
}) {
  const subcatNames = category.sous_categories?.slice(0, 3).map(s => s.nom).join(', ') ?? ''
  const hasMore = (category.sous_categories?.length ?? 0) > 3

  return (
    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
      {/* Ligne principale */}
      <div className="flex items-center gap-4 p-4 group">
        {/* Chevron */}
        <button onClick={onExpand} className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0">
          {expanded
            ? <ChevronDown className="w-4 h-4 text-gray-600" strokeWidth={2} />
            : <ChevronRight className="w-4 h-4 text-gray-500" strokeWidth={2} />
          }
        </button>

        {/* Image */}
        <div className="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
          {category.image
            ? <img src={category.image} alt={category.nom} className="w-full h-full object-cover" />
            : <Folder className="w-5 h-5 text-gray-500" strokeWidth={1.5} />
          }
        </div>

        {/* Nom + slug */}
        <div className="flex-1 min-w-0">
          <p className="text-sm font-semibold text-gray-900">{category.nom}</p>
          <p className="text-[11px] text-gray-500">{category.slug}</p>
        </div>

        {/* Aperçu sous-catégories */}
        <div className="hidden md:block flex-shrink-0 min-w-0 w-36">
          <p className="text-xs font-medium text-gray-900">
            {category.sous_categories?.length ?? 0} sous-cat.
          </p>
          {subcatNames && (
            <p className="text-[11px] text-gray-500 truncate">
              {subcatNames}{hasMore ? '…' : ''}
            </p>
          )}
        </div>

        {/* Nombre de produits */}
        <div className="hidden sm:flex items-center gap-1.5 flex-shrink-0">
          <Package className="w-3.5 h-3.5 text-gray-500" strokeWidth={1.5} />
          <span className="text-xs text-gray-500">{category.produits_count ?? 0}</span>
        </div>

        {/* Statut */}
        <StatusBadge active={category.est_active} />

        {/* Actions */}
        <div className="flex items-center gap-1 flex-shrink-0">
          <button
            onClick={onAddSubcategory}
            className="hidden sm:flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-[11px] font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors"
            title="Ajouter sous-catégorie"
          >
            <Plus className="w-3 h-3" strokeWidth={2.5} />
            <span className="hidden lg:inline">Sous-cat.</span>
          </button>
          <button
            onClick={onToggle}
            className="p-2 rounded-xl hover:bg-gray-100 transition-colors"
            title={category.est_active ? 'Désactiver' : 'Activer'}
          >
            {category.est_active
              ? <ToggleRight className="w-4 h-4 text-gray-600" strokeWidth={1.5} />
              : <ToggleLeft className="w-4 h-4 text-gray-500" strokeWidth={1.5} />
            }
          </button>
          <button onClick={onEdit} className="p-2 rounded-xl hover:bg-gray-100 transition-colors">
            <Edit2 className="w-4 h-4 text-gray-600" strokeWidth={1.5} />
          </button>
          <button onClick={onDelete} className="p-2 rounded-xl hover:bg-rose-50 transition-colors">
            <Trash2 className="w-4 h-4 text-rose-400" strokeWidth={1.5} />
          </button>
        </div>
      </div>

      {/* Accordéon : sous-catégories */}
      {expanded && (
        <div className="px-4 pb-4 space-y-2 border-t border-gray-200 pt-3 ml-8">
          {(category.sous_categories?.length ?? 0) === 0 ? (
            <div className="flex items-center gap-3 py-3 px-4">
              <FolderOpen className="w-4 h-4 text-gray-500" strokeWidth={1.5} />
              <p className="text-sm text-gray-500">Aucune sous-catégorie</p>
              <button
                onClick={onAddSubcategory}
                className="ml-auto text-xs font-semibold text-[#e91e63] hover:text-[#d81b60] transition-colors"
              >
                + Ajouter
              </button>
            </div>
          ) : (
            <>
              {category.sous_categories!.map(s => (
                <SubcatRow
                  key={s.id}
                  subcat={s}
                  onEdit={() => onEditSubcategory(s)}
                  onDelete={() => onDeleteSubcategory(s)}
                  onToggle={() => onToggleSubcategory(s)}
                />
              ))}
              <button
                onClick={onAddSubcategory}
                className="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl border border-dashed border-gray-300 text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors"
              >
                <Plus className="w-3.5 h-3.5" strokeWidth={2.5} />
                Ajouter une sous-catégorie
              </button>
            </>
          )}
        </div>
      )}
    </div>
  )
}

// ─── Form Modal ───────────────────────────────────────────────────────────────

function FormModal({ mode, parentCategories, onClose, onSuccess }: {
  mode: ModalMode
  parentCategories: Pick<Categorie, 'id' | 'nom'>[]
  onClose: () => void
  onSuccess: () => void
}) {
  const isEdit = mode.kind === 'edit-category' || mode.kind === 'edit-subcategory'
  const isSubcat = mode.kind === 'create-subcategory' || mode.kind === 'edit-subcategory'

  const existingItem = mode.kind === 'edit-category'
    ? mode.category
    : mode.kind === 'edit-subcategory' ? mode.subcategory : null

  const defaultParentId = mode.kind === 'create-subcategory'
    ? mode.parentId
    : mode.kind === 'edit-subcategory' ? mode.subcategory.parent_id : null

  const [nom, setNom] = useState(existingItem?.nom ?? '')
  const [description, setDescription] = useState(existingItem?.description ?? '')
  const [estActive, setEstActive] = useState(existingItem?.est_active ?? true)
  const [estPopulaire, setEstPopulaire] = useState(
    mode.kind === 'edit-category' ? (mode.category.est_populaire ?? false) : false
  )
  const [ordre, setOrdre] = useState(
    mode.kind === 'edit-category' ? (mode.category.ordre_affichage ?? 0) : 0
  )
  const [parentId, setParentId] = useState<number | null>(defaultParentId)
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(existingItem?.image ?? null)

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const fileRef = useRef<HTMLInputElement>(null)

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    setImageFile(file)
    setImagePreview(URL.createObjectURL(file))
  }

  const title =
    mode.kind === 'create-category' ? 'Nouvelle catégorie' :
    mode.kind === 'create-subcategory' ? `Sous-catégorie — ${mode.parentNom}` :
    mode.kind === 'edit-category' ? `Modifier — ${mode.category.nom}` :
    `Modifier — ${mode.subcategory.nom}`

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    if (!nom.trim()) { setError('Le nom est obligatoire.'); return }
    if (isSubcat && !parentId) { setError('La catégorie parente est obligatoire.'); return }

    setSubmitting(true)
    try {
      const fd = new FormData()
      fd.append('nom', nom.trim())
      if (description) fd.append('description', description)
      fd.append('est_active', estActive ? '1' : '0')
      if (!isSubcat) {
        fd.append('est_populaire', estPopulaire ? '1' : '0')
        fd.append('ordre_affichage', String(ordre))
      }
      if (isSubcat && parentId) fd.append('parent_id', String(parentId))
      if (imageFile) fd.append('image', imageFile)

      if (isEdit && existingItem) {
        await updateCategory(existingItem.id, fd)
      } else {
        await createCategory(fd)
      }

      onSuccess()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
        ?.response?.data
      if (msg?.errors) {
        setError(Object.values(msg.errors).flat().join(' '))
      } else {
        setError(msg?.message ?? 'Une erreur est survenue.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div
      className="fixed inset-0 bg-black/30 backdrop-blur-[2px] z-50 flex items-center justify-center p-4"
      onClick={(e) => { if (e.target === e.currentTarget) onClose() }}
    >
      <div className="bg-white rounded-3xl border border-gray-200 shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-200">
          <div>
            <h2 className="font-bold text-gray-900 text-lg">{title}</h2>
            <p className="text-xs text-gray-500 mt-0.5">
              {isSubcat ? 'Créer une sous-catégorie pour organiser vos produits' : 'Renseigner les informations de la catégorie'}
            </p>
          </div>
          <button onClick={onClose} className="p-2 rounded-xl hover:bg-gray-100 transition-colors">
            <X className="w-4 h-4 text-gray-500" strokeWidth={1.5} />
          </button>
        </div>

        {/* Formulaire */}
        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {/* Image */}
          <div>
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
              Image
            </label>
            <div
              onClick={() => fileRef.current?.click()}
              className="relative w-full h-32 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center cursor-pointer hover:bg-gray-100 transition-colors overflow-hidden"
            >
              {imagePreview ? (
                <img src={imagePreview} alt="aperçu" className="w-full h-full object-cover" />
              ) : (
                <div className="flex flex-col items-center gap-2">
                  <ImageIcon className="w-6 h-6 text-gray-400" strokeWidth={1.5} />
                  <span className="text-xs text-gray-500">Cliquer pour uploader</span>
                </div>
              )}
              {imagePreview && (
                <button
                  type="button"
                  onClick={(e) => { e.stopPropagation(); setImageFile(null); setImagePreview(null) }}
                  className="absolute top-2 right-2 p-1 bg-black/40 text-white rounded-full hover:bg-black/60"
                >
                  <X className="w-3 h-3" />
                </button>
              )}
            </div>
            <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={handleImageChange} />
          </div>

          {/* Nom */}
          <div>
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
              Nom <span className="text-rose-400">*</span>
            </label>
            <input
              type="text"
              value={nom}
              onChange={e => setNom(e.target.value)}
              placeholder={isSubcat ? 'Ex: Huiles capillaires' : 'Ex: Soins cheveux'}
              className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5] transition-all"
            />
          </div>

          {/* Description */}
          <div>
            <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
              Description
            </label>
            <textarea
              value={description ?? ''}
              onChange={e => setDescription(e.target.value)}
              placeholder="Description courte…"
              rows={3}
              className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5] transition-all resize-none"
            />
          </div>

          {/* Catégorie parente (sous-catégorie uniquement) */}
          {isSubcat && (
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                Catégorie parente <span className="text-rose-400">*</span>
              </label>
              <select
                value={parentId ?? ''}
                onChange={e => setParentId(Number(e.target.value) || null)}
                className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5] transition-all"
              >
                <option value="">Sélectionner une catégorie…</option>
                {parentCategories.map(c => (
                  <option key={c.id} value={c.id}>{c.nom}</option>
                ))}
              </select>
            </div>
          )}

          {/* Ordre (catégorie uniquement) */}
          {!isSubcat && (
            <div>
              <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                Ordre d'affichage
              </label>
              <input
                type="number"
                min={0}
                max={999}
                value={ordre}
                onChange={e => setOrdre(Number(e.target.value))}
                className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5] transition-all"
              />
            </div>
          )}

          {/* Toggles */}
          <div className="flex gap-4">
            <label className="flex-1 flex items-center justify-between px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
              <span className="text-sm font-medium text-gray-900">Actif</span>
              <div
                onClick={() => setEstActive(v => !v)}
                className={`w-10 h-6 rounded-full transition-colors relative ${estActive ? 'bg-[#e91e63]' : 'bg-gray-300'}`}
              >
                <span className={`absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${estActive ? 'translate-x-5' : 'translate-x-1'}`} />
              </div>
            </label>

            {!isSubcat && (
              <label className="flex-1 flex items-center justify-between px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                <span className="text-sm font-medium text-gray-900">Populaire</span>
                <div
                  onClick={() => setEstPopulaire(v => !v)}
                  className={`w-10 h-6 rounded-full transition-colors relative ${estPopulaire ? 'bg-[#e91e63]' : 'bg-gray-300'}`}
                >
                  <span className={`absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${estPopulaire ? 'translate-x-5' : 'translate-x-1'}`} />
                </div>
              </label>
            )}
          </div>

          {/* Erreur */}
          {error && (
            <div className="flex items-center gap-2.5 px-4 py-3 bg-rose-50 border border-rose-200 rounded-xl">
              <AlertTriangle className="w-4 h-4 text-rose-500 flex-shrink-0" strokeWidth={1.5} />
              <p className="text-sm text-rose-600">{error}</p>
            </div>
          )}

          {/* Actions */}
          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-500 hover:bg-gray-100 transition-colors"
            >
              Annuler
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="flex-1 py-3 rounded-xl bg-[#e91e63] text-white text-sm font-semibold hover:bg-[#d81b60] transition-colors disabled:opacity-50 shadow-sm"
            >
              {submitting ? 'Enregistrement…' : isEdit ? 'Enregistrer' : 'Créer'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ─── Delete Dialog ────────────────────────────────────────────────────────────

function DeleteDialog({ target, loading, onConfirm, onCancel }: {
  target: DeleteTarget
  loading: boolean
  onConfirm: () => void
  onCancel: () => void
}) {
  return (
    <div className="fixed inset-0 bg-black/30 backdrop-blur-[2px] z-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-3xl border border-gray-200 shadow-xl w-full max-w-sm p-6">
        <div className="w-12 h-12 bg-rose-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <Trash2 className="w-5 h-5 text-rose-500" strokeWidth={1.5} />
        </div>
        <h3 className="font-bold text-gray-900 text-center mb-1">
          Supprimer {target.type === 'category' ? 'la catégorie' : 'la sous-catégorie'} ?
        </h3>
        <p className="text-sm text-gray-500 text-center mb-5">
          « {target.nom} » sera supprimé définitivement. Cette action est irréversible.
        </p>
        <div className="flex gap-3">
          <button
            onClick={onCancel}
            className="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-500 hover:bg-gray-100 transition-colors"
          >
            Annuler
          </button>
          <button
            onClick={onConfirm}
            disabled={loading}
            className="flex-1 py-2.5 rounded-xl bg-rose-500 text-white text-sm font-semibold hover:bg-rose-600 transition-colors disabled:opacity-50"
          >
            {loading ? 'Suppression…' : 'Supprimer'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ─── Toast ───────────────────────────────────────────────────────────────────

function Toast({ message, type, onDismiss }: { message: string; type: 'success' | 'error'; onDismiss: () => void }) {
  return (
    <div className={`flex items-center gap-3 px-4 py-3 rounded-2xl shadow-lg border text-sm font-medium
      ${type === 'success' ? 'bg-white border-gray-200 text-gray-900' : 'bg-rose-50 border-rose-200 text-rose-700'}`}>
      {type === 'success'
        ? <CheckCircle2 className="w-4 h-4 text-green-500 flex-shrink-0" strokeWidth={1.5} />
        : <AlertTriangle className="w-4 h-4 text-rose-500 flex-shrink-0" strokeWidth={1.5} />
      }
      <span className="flex-1">{message}</span>
      <button onClick={onDismiss} className="ml-1 p-0.5 rounded hover:opacity-60 transition-opacity">
        <X className="w-3 h-3" strokeWidth={2} />
      </button>
    </div>
  )
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export function CategoriesPage() {
  const [categories, setCategories] = useState<Categorie[]>([])
  const [stats, setStats] = useState<CategoryStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [statsLoading, setStatsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('all')
  const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set())
  const [modal, setModal] = useState<ModalMode | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null)
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [toasts, setToasts] = useState<ToastItem[]>([])

  const addToast = (message: string, type: 'success' | 'error' = 'success') => {
    const id = Date.now()
    setToasts(prev => [...prev, { id, message, type }])
    setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), 3500)
  }
  const removeToast = (id: number) => setToasts(prev => prev.filter(t => t.id !== id))

  const loadData = async () => {
    setLoading(true)
    setStatsLoading(true)
    setError(null)
    try {
      const [cats, statsData] = await Promise.all([
        getCategoriesParents(),
        getCategoryStats(),
      ])
      setCategories(cats)
      setStats(statsData)
    } catch (err) {
      console.error('[CategoriesPage] loadData error:', err)
      setError('Impossible de charger les catégories.')
    } finally {
      setLoading(false)
      setStatsLoading(false)
    }
  }

  useEffect(() => { loadData() }, [])

  const toggleExpand = (id: number) => {
    setExpandedIds(prev => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const handleToggleStatus = async (id: number, isSubcat = false, parentId?: number) => {
    try {
      const updated = await toggleCategoryStatus(id)
      addToast(updated.est_active ? 'Activé avec succès !' : 'Désactivé avec succès !')
      setCategories(prev => prev.map(cat => {
        if (!isSubcat && cat.id === id) return { ...cat, est_active: updated.est_active }
        if (isSubcat && cat.id === parentId) {
          return {
            ...cat,
            sous_categories: cat.sous_categories?.map(s =>
              s.id === id ? { ...s, est_active: updated.est_active } : s
            )
          }
        }
        return cat
      }))
    } catch {
      addToast('Impossible de modifier le statut.', 'error')
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleteLoading(true)
    const nom = deleteTarget.nom
    try {
      await deleteCategory(deleteTarget.id)
      setDeleteTarget(null)
      addToast(`« ${nom} » supprimé avec succès.`)
      await loadData()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      addToast(msg ?? 'Impossible de supprimer.', 'error')
    } finally {
      setDeleteLoading(false)
    }
  }

  const handleModalSuccess = async () => {
    const message =
      modal?.kind === 'create-category' ? 'Catégorie créée avec succès !' :
      modal?.kind === 'create-subcategory' ? 'Sous-catégorie créée avec succès !' :
      modal?.kind === 'edit-category' ? 'Catégorie modifiée avec succès !' :
      'Sous-catégorie modifiée avec succès !'
    setModal(null)
    addToast(message)
    await loadData()
  }

  const filtered = useMemo(() => {
    return categories.filter(cat => {
      const matchSearch = !search ||
        cat.nom.toLowerCase().includes(search.toLowerCase()) ||
        cat.sous_categories?.some(s => s.nom.toLowerCase().includes(search.toLowerCase()))
      const matchStatus = statusFilter === 'all' ||
        (statusFilter === 'active' ? cat.est_active : !cat.est_active)
      return matchSearch && matchStatus
    })
  }, [categories, search, statusFilter])

  const parentCategoryOptions = categories.map(c => ({ id: c.id, nom: c.nom }))

  if (error) {
    return (
      <EcommerceLayout>
        <div className="p-8 flex items-center justify-center min-h-[40vh]">
          <div className="text-center max-w-sm">
            <div className="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <AlertTriangle className="w-6 h-6 text-rose-500" />
            </div>
            <h3 className="text-sm font-semibold text-gray-900 mb-1.5">Erreur de chargement</h3>
            <p className="text-sm text-gray-500 mb-5">{error}</p>
            <button
              onClick={loadData}
              className="px-5 py-2.5 bg-[#e91e63] text-white text-xs font-semibold rounded-xl hover:bg-[#d81b60] transition-colors"
            >
              Réessayer
            </button>
          </div>
        </div>
      </EcommerceLayout>
    )
  }

  return (
    <EcommerceLayout>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Gestion des catégories</h1>
          <p className="text-sm text-gray-500 mt-1">
            Organisez les catégories et sous-catégories de votre boutique.
          </p>
        </div>
        <div className="flex items-center gap-2 flex-shrink-0">
          <button
            onClick={() => setModal({ kind: 'create-subcategory', parentId: categories[0]?.id ?? 0, parentNom: categories[0]?.nom ?? '' })}
            disabled={categories.length === 0}
            className="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          >
            <Plus className="w-3.5 h-3.5" strokeWidth={2.5} />
            Sous-catégorie
          </button>
          <button
            onClick={() => setModal({ kind: 'create-category' })}
            className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#e91e63] text-white text-sm font-semibold hover:bg-[#d81b60] transition-colors shadow-sm"
          >
            <Plus className="w-3.5 h-3.5" strokeWidth={2.5} />
            Catégorie
          </button>
        </div>
      </div>

      {/* Cartes stats */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <StatCard title="Catégories" value={stats?.total_categories ?? 0} icon={Folder} iconBg="bg-gray-100" iconColor="text-gray-600" loading={statsLoading} />
        <StatCard title="Sous-catégories" value={stats?.total_sous_categories ?? 0} icon={Layers} iconBg="bg-pink-50" iconColor="text-[#e91e63]" loading={statsLoading} />
        <StatCard title="Produits liés" value={stats?.total_produits ?? 0} icon={Package} iconBg="bg-sky-50" iconColor="text-sky-500" loading={statsLoading} />
        <StatCard title="Actives" value={stats?.categories_actives ?? 0} icon={Star} iconBg="bg-green-100" iconColor="text-green-600" loading={statsLoading} />
      </div>

      {/* Recherche / Filtres */}
      <div className="flex flex-col sm:flex-row gap-3 mb-6">
        <div className="relative flex-1">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Rechercher une catégorie…"
            className="w-full pl-10 pr-4 py-2.5 text-sm bg-white border border-gray-200 rounded-xl text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5] transition-all"
          />
        </div>

        <div className="flex gap-2">
          {(['all', 'active', 'inactive'] as const).map(f => (
            <button
              key={f}
              onClick={() => setStatusFilter(f)}
              className={`px-3 py-2.5 rounded-xl text-xs font-semibold transition-colors ${
                statusFilter === f
                  ? 'bg-[#e91e63] text-white shadow-sm'
                  : 'bg-white border border-gray-200 text-gray-500 hover:bg-gray-100'
              }`}
            >
              {f === 'all' ? 'Tous' : f === 'active' ? 'Actifs' : 'Masqués'}
            </button>
          ))}

          <button
            onClick={loadData}
            disabled={loading}
            className="p-2.5 bg-white border border-gray-200 rounded-xl hover:bg-gray-100 transition-colors disabled:opacity-40"
          >
            <RefreshCw className={`w-4 h-4 text-gray-500 ${loading ? 'animate-spin' : ''}`} strokeWidth={1.5} />
          </button>
        </div>
      </div>

      {/* Liste */}
      {loading ? (
        <div className="space-y-3">
          {[1, 2, 3].map(i => (
            <div key={i} className="bg-white rounded-2xl border border-gray-200 p-4">
              <div className="flex items-center gap-4">
                <Skeleton className="w-6 h-6 rounded" />
                <Skeleton className="w-10 h-10 rounded-xl" />
                <div className="flex-1">
                  <Skeleton className="h-4 w-32 mb-1.5" />
                  <Skeleton className="h-3 w-20" />
                </div>
                <Skeleton className="h-6 w-20 rounded-full" />
              </div>
            </div>
          ))}
        </div>
      ) : filtered.length === 0 ? (
        <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center">
          <Tag className="w-8 h-8 text-gray-300 mx-auto mb-3" strokeWidth={1.5} />
          <p className="text-sm font-medium text-gray-900 mb-1">Aucune catégorie</p>
          <p className="text-xs text-gray-500 mb-4">
            {search ? 'Aucun résultat pour cette recherche.' : 'Créez votre première catégorie.'}
          </p>
          {!search && (
            <button
              onClick={() => setModal({ kind: 'create-category' })}
              className="px-4 py-2 bg-[#e91e63] text-white text-xs font-semibold rounded-xl hover:bg-[#d81b60] transition-colors"
            >
              + Nouvelle catégorie
            </button>
          )}
        </div>
      ) : (
        <div className="space-y-3">
          {filtered.map(cat => (
            <CategoryRow
              key={cat.id}
              category={cat}
              expanded={expandedIds.has(cat.id)}
              onExpand={() => toggleExpand(cat.id)}
              onEdit={() => setModal({ kind: 'edit-category', category: cat })}
              onDelete={() => setDeleteTarget({ type: 'category', id: cat.id, nom: cat.nom })}
              onToggle={() => handleToggleStatus(cat.id)}
              onAddSubcategory={() => setModal({ kind: 'create-subcategory', parentId: cat.id, parentNom: cat.nom })}
              onEditSubcategory={s => setModal({ kind: 'edit-subcategory', subcategory: s, parentNom: cat.nom })}
              onDeleteSubcategory={s => setDeleteTarget({ type: 'subcategory', id: s.id, nom: s.nom })}
              onToggleSubcategory={s => handleToggleStatus(s.id, true, cat.id)}
            />
          ))}
        </div>
      )}

      {/* Modales */}
      {modal && (
        <FormModal
          mode={modal}
          parentCategories={parentCategoryOptions}
          onClose={() => setModal(null)}
          onSuccess={handleModalSuccess}
        />
      )}

      {deleteTarget && (
        <DeleteDialog
          target={deleteTarget}
          loading={deleteLoading}
          onConfirm={handleDelete}
          onCancel={() => setDeleteTarget(null)}
        />
      )}

      {/* Toasts */}
      <div className="fixed bottom-6 right-6 flex flex-col gap-2 z-[200] min-w-[280px] max-w-[360px]">
        {toasts.map(t => (
          <Toast key={t.id} message={t.message} type={t.type} onDismiss={() => removeToast(t.id)} />
        ))}
      </div>
    </EcommerceLayout>
  )
}
