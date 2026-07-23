import { useState, useEffect, useRef } from 'react'
import {
  Plus, Search, Package, Eye, EyeOff, Copy, Trash2,
  AlertTriangle, RefreshCw, TrendingUp, ImageIcon,
  CheckCircle2, X, ChevronLeft, ChevronRight,
} from 'lucide-react'
import { EcommerceLayout } from './components/EcommerceLayout'
import { ProductFormModal } from './components/ProductFormModal'
import {
  getProduits, getProduitById, deleteProduit, toggleProduitStatus,
  duplicateProduit, getCategoryOptions,
} from './ecommerce.api'
import type { Produit, CategoryOption } from './ecommerce.types'

// ─── Types ────────────────────────────────────────────────────────────────────

interface ToastItem { id: number; message: string; type: 'success' | 'error' }
interface ProduitStats { total: number; visibles: number; masques: number; enPromo: number }

// ─── StatCard ────────────────────────────────────────────────────────────────

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

// ─── ProductCard ──────────────────────────────────────────────────────────────

function ProductCard({ produit, onEdit, onDelete, onDuplicate, onToggle }: {
  produit: Produit
  onEdit: () => void; onDelete: () => void; onDuplicate: () => void; onToggle: () => void
}) {
  const status = produit.stock_status?.status ?? 'unlimited'
  const stockCls = status === 'in_stock' ? 'bg-green-100 text-green-700'
    : status === 'low_stock' ? 'bg-amber-100 text-amber-700'
    : status === 'out_of_stock' ? 'bg-rose-100 text-rose-600'
    : 'bg-gray-100 text-gray-500'

  const dotCls = status === 'in_stock' ? 'bg-green-500'
    : status === 'low_stock' ? 'bg-amber-500'
    : status === 'out_of_stock' ? 'bg-rose-400'
    : 'bg-gray-400'

  const prixActuel = Number(produit.prix_actuel ?? produit.prix)

  return (
    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden group hover:shadow-md transition-shadow">
      {/* Image */}
      <div className="relative aspect-square bg-gray-100 overflow-hidden">
        {produit.image_principale
          ? <img src={produit.image_principale} alt={produit.nom}
              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
          : <div className="w-full h-full flex items-center justify-center">
              <ImageIcon className="w-10 h-10 text-gray-300" strokeWidth={1.5} />
            </div>
        }

        {/* Badges */}
        <div className="absolute top-2 left-2 flex flex-col gap-1">
          {produit.en_promo && (
            <span className="px-2 py-0.5 text-[10px] font-bold bg-rose-500 text-white rounded-full">Promo</span>
          )}
          {produit.est_nouveaute && (
            <span className="px-2 py-0.5 text-[10px] font-bold bg-sky-500 text-white rounded-full">Nouveau</span>
          )}
        </div>

        {/* Visibility */}
        <button
          onClick={onToggle}
          className="absolute top-2 right-2 p-1.5 bg-white/80 rounded-lg hover:bg-white transition-colors"
          title={produit.est_visible ? 'Masquer' : 'Afficher'}
        >
          {produit.est_visible
            ? <Eye className="w-3.5 h-3.5 text-green-600" strokeWidth={1.5} />
            : <EyeOff className="w-3.5 h-3.5 text-gray-500" strokeWidth={1.5} />
          }
        </button>
      </div>

      {/* Content */}
      <div className="p-3">
        <p className="text-[11px] text-gray-500 truncate mb-0.5">{produit.categorie?.nom ?? '—'}</p>
        <h3 className="font-semibold text-sm text-gray-900 truncate mb-2" title={produit.nom}>{produit.nom}</h3>

        {/* Price */}
        <div className="flex items-baseline gap-1.5 mb-2">
          <span className="font-bold text-sm text-gray-900">{prixActuel.toLocaleString('fr-FR')} FCFA</span>
          {produit.en_promo && (
            <span className="text-xs text-gray-400 line-through">{Number(produit.prix).toLocaleString('fr-FR')}</span>
          )}
        </div>

        {/* Stock */}
        <div className="mb-3">
          <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold ${stockCls}`}>
            <span className={`w-1.5 h-1.5 rounded-full ${dotCls}`} />
            {produit.stock_status?.label ?? `Stock : ${produit.stock_disponible}`}
          </span>
        </div>

        {/* Actions */}
        <div className="flex gap-1">
          <button onClick={onEdit}
            className="flex-1 py-1.5 rounded-xl bg-[#e91e63] text-white text-xs font-semibold hover:bg-[#d81b60] transition-colors">
            Modifier
          </button>
          <button onClick={onDuplicate} title="Dupliquer"
            className="p-1.5 rounded-xl border border-gray-200 hover:bg-gray-100 transition-colors">
            <Copy className="w-3.5 h-3.5 text-gray-500" strokeWidth={1.5} />
          </button>
          <button onClick={onDelete} title="Supprimer"
            className="p-1.5 rounded-xl border border-rose-200 hover:bg-rose-50 transition-colors">
            <Trash2 className="w-3.5 h-3.5 text-rose-400" strokeWidth={1.5} />
          </button>
        </div>
      </div>
    </div>
  )
}

// ─── Skeleton ─────────────────────────────────────────────────────────────────

function CardSkeleton() {
  return (
    <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div className="aspect-square bg-gray-100 animate-pulse" />
      <div className="p-3 space-y-2">
        <div className="h-3 w-20 bg-gray-100 rounded animate-pulse" />
        <div className="h-4 w-32 bg-gray-100 rounded animate-pulse" />
        <div className="h-4 w-24 bg-gray-100 rounded animate-pulse" />
        <div className="h-7 bg-gray-100 rounded-xl animate-pulse mt-3" />
      </div>
    </div>
  )
}

// ─── DeleteDialog ─────────────────────────────────────────────────────────────

function DeleteDialog({ nom, loading, onConfirm, onCancel }: {
  nom: string; loading: boolean; onConfirm: () => void; onCancel: () => void
}) {
  return (
    <div className="fixed inset-0 bg-black/30 backdrop-blur-[2px] z-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-3xl border border-gray-200 shadow-xl w-full max-w-sm p-6">
        <div className="w-12 h-12 bg-rose-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
          <Trash2 className="w-5 h-5 text-rose-500" strokeWidth={1.5} />
        </div>
        <h3 className="font-bold text-gray-900 text-center mb-1">Supprimer le produit ?</h3>
        <p className="text-sm text-gray-500 text-center mb-5">
          « {nom} » sera supprimé définitivement. Cette action est irréversible.
        </p>
        <div className="flex gap-3">
          <button onClick={onCancel}
            className="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-500 hover:bg-gray-100 transition-colors">
            Annuler
          </button>
          <button onClick={onConfirm} disabled={loading}
            className="flex-1 py-2.5 rounded-xl bg-rose-500 text-white text-sm font-semibold hover:bg-rose-600 transition-colors disabled:opacity-50">
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

export function ProduitsPage() {
  const [produits, setProduits] = useState<Produit[]>([])
  const [stats, setStats] = useState<ProduitStats>({ total: 0, visibles: 0, masques: 0, enPromo: 0 })
  const [categories, setCategories] = useState<CategoryOption[]>([])
  const [loading, setLoading] = useState(true)
  const [statsLoading, setStatsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [modal, setModal] = useState<'create' | Produit | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<{ id: number; nom: string } | null>(null)
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [toasts, setToasts] = useState<ToastItem[]>([])
  const skipFirstRef = useRef(true)

  const addToast = (message: string, type: 'success' | 'error' = 'success') => {
    const id = Date.now()
    setToasts(prev => [...prev, { id, message, type }])
    setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), 3500)
  }
  const removeToast = (id: number) => setToasts(prev => prev.filter(t => t.id !== id))

  const loadStats = async () => {
    setStatsLoading(true)
    try {
      const [all, vis] = await Promise.all([
        getProduits(1, 1),
        getProduits(1, 1, '', '', 'visible'),
      ])
      setStats(prev => ({ ...prev, total: all.total, visibles: vis.total, masques: all.total - vis.total }))
    } catch { /* stats non bloquantes */ }
    finally { setStatsLoading(false) }
  }

  const loadCategories = async () => {
    try {
      setCategories(await getCategoryOptions())
    } catch { /* non bloquant */ }
  }

  const loadProduits = async (p: number) => {
    setLoading(true)
    try {
      const res = await getProduits(p, 12, search, categoryFilter, statusFilter)
      setProduits(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
      setStats(prev => ({ ...prev, enPromo: res.data.filter(x => x.en_promo).length }))
    } catch {
      addToast('Impossible de charger les produits.', 'error')
    } finally {
      setLoading(false)
    }
  }

  // Chargement initial
  useEffect(() => {
    loadStats()
    loadCategories()
    loadProduits(1)
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  // Rechargement piloté par les filtres (debounce sur la recherche)
  useEffect(() => {
    if (skipFirstRef.current) { skipFirstRef.current = false; return }
    const delay = search ? 400 : 0
    const timer = setTimeout(() => { setPage(1); loadProduits(1) }, delay)
    return () => clearTimeout(timer)
  }, [search, categoryFilter, statusFilter]) // eslint-disable-line react-hooks/exhaustive-deps

  const handlePageChange = (p: number) => { setPage(p); loadProduits(p) }

  const handleEdit = async (id: number) => {
    try {
      setModal(await getProduitById(id))
    } catch {
      addToast('Impossible de charger le produit.', 'error')
    }
  }

  const handleToggle = async (id: number) => {
    try {
      await toggleProduitStatus(id)
      setProduits(prev => prev.map(p => p.id === id ? { ...p, est_visible: !p.est_visible } : p))
      addToast('Visibilité modifiée.')
    } catch {
      addToast('Impossible de modifier la visibilité.', 'error')
    }
  }

  const handleDuplicate = async (id: number) => {
    try {
      await duplicateProduit(id)
      addToast('Produit dupliqué avec succès !')
      loadProduits(page)
      loadStats()
    } catch {
      addToast('Impossible de dupliquer le produit.', 'error')
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setDeleteLoading(true)
    const nom = deleteTarget.nom
    try {
      await deleteProduit(deleteTarget.id)
      setDeleteTarget(null)
      addToast(`« ${nom} » supprimé avec succès.`)
      loadProduits(page)
      loadStats()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      addToast(msg ?? 'Impossible de supprimer.', 'error')
    } finally {
      setDeleteLoading(false)
    }
  }

  const handleModalSuccess = (message: string) => {
    setModal(null)
    addToast(message)
    loadProduits(page)
    loadStats()
  }

  const parentCats = categories.filter(c => !c.parent_id)
  const childrenOf = (pid: number) => categories.filter(c => c.parent_id === pid)

  // Numéros de pagination (1 … n avec ellipses)
  const pages = Array.from({ length: lastPage }, (_, i) => i + 1)
    .filter(p => p === 1 || p === lastPage || Math.abs(p - page) <= 1)
    .reduce<(number | '…')[]>((acc, p, idx, arr) => {
      if (idx > 0 && (arr[idx - 1] as number) !== p - 1) acc.push('…')
      acc.push(p)
      return acc
    }, [])

  return (
    <EcommerceLayout>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Gestion des produits</h1>
          <p className="text-sm text-gray-500 mt-1">
            {loading ? '…' : `${total} produit${total !== 1 ? 's' : ''} au total`}
          </p>
        </div>
        <button
          onClick={() => setModal('create')}
          className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#e91e63] text-white text-sm font-semibold hover:bg-[#d81b60] transition-colors shadow-sm"
        >
          <Plus className="w-3.5 h-3.5" strokeWidth={2.5} />
          Nouveau produit
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <StatCard title="Total" value={stats.total} icon={Package} iconBg="bg-gray-100" iconColor="text-gray-600" loading={statsLoading} />
        <StatCard title="Visibles" value={stats.visibles} icon={Eye} iconBg="bg-green-100" iconColor="text-green-600" loading={statsLoading} />
        <StatCard title="Masqués" value={stats.masques} icon={EyeOff} iconBg="bg-rose-100" iconColor="text-rose-500" loading={statsLoading} />
        <StatCard title="En promo" value={stats.enPromo} icon={TrendingUp} iconBg="bg-sky-50" iconColor="text-sky-500" loading={statsLoading} />
      </div>

      {/* Filtres */}
      <div className="flex flex-col sm:flex-row gap-3 mb-6">
        <div className="relative flex-1">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Rechercher un produit…"
            className="w-full pl-10 pr-4 py-2.5 text-sm bg-white border border-gray-200 rounded-xl text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5] transition-all"
          />
        </div>

        <div className="flex gap-2 flex-wrap">
          {/* Catégories */}
          <select
            value={categoryFilter}
            onChange={e => setCategoryFilter(e.target.value)}
            className="px-3 py-2.5 rounded-xl text-xs font-semibold bg-white border border-gray-200 text-gray-500 focus:outline-none focus:border-[#ff5ca5] transition-colors"
          >
            <option value="">Toutes catégories</option>
            {parentCats.map(p => (
              <optgroup key={p.id} label={p.nom}>
                <option value={p.id}>{p.nom}</option>
                {childrenOf(p.id).map(c => (
                  <option key={c.id} value={c.id}>&nbsp;&nbsp;└ {c.nom}</option>
                ))}
              </optgroup>
            ))}
          </select>

          {/* Statuts */}
          {(['', 'visible', 'hidden'] as const).map(f => (
            <button
              key={f}
              onClick={() => setStatusFilter(f)}
              className={`px-3 py-2.5 rounded-xl text-xs font-semibold transition-colors ${
                statusFilter === f
                  ? 'bg-[#e91e63] text-white shadow-sm'
                  : 'bg-white border border-gray-200 text-gray-500 hover:bg-gray-100'
              }`}
            >
              {f === '' ? 'Tous' : f === 'visible' ? 'Visibles' : 'Masqués'}
            </button>
          ))}

          <button
            onClick={() => { loadProduits(page); loadStats() }}
            disabled={loading}
            className="p-2.5 bg-white border border-gray-200 rounded-xl hover:bg-gray-100 transition-colors disabled:opacity-40"
          >
            <RefreshCw className={`w-4 h-4 text-gray-500 ${loading ? 'animate-spin' : ''}`} strokeWidth={1.5} />
          </button>
        </div>
      </div>

      {/* Grille */}
      {loading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
          {Array.from({ length: 8 }).map((_, i) => <CardSkeleton key={i} />)}
        </div>
      ) : produits.length === 0 ? (
        <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center">
          <Package className="w-8 h-8 text-gray-300 mx-auto mb-3" strokeWidth={1.5} />
          <p className="text-sm font-medium text-gray-900 mb-1">Aucun produit</p>
          <p className="text-xs text-gray-500 mb-4">
            {search ? 'Aucun résultat pour cette recherche.' : 'Ajoutez votre premier produit.'}
          </p>
          {!search && (
            <button
              onClick={() => setModal('create')}
              className="px-4 py-2 bg-[#e91e63] text-white text-xs font-semibold rounded-xl hover:bg-[#d81b60] transition-colors"
            >
              + Nouveau produit
            </button>
          )}
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
          {produits.map(p => (
            <ProductCard
              key={p.id}
              produit={p}
              onEdit={() => handleEdit(p.id)}
              onDelete={() => setDeleteTarget({ id: p.id, nom: p.nom })}
              onDuplicate={() => handleDuplicate(p.id)}
              onToggle={() => handleToggle(p.id)}
            />
          ))}
        </div>
      )}

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2 mt-8">
          <button
            onClick={() => handlePageChange(page - 1)}
            disabled={page <= 1}
            className="p-2 rounded-xl border border-gray-200 hover:bg-gray-100 transition-colors disabled:opacity-40"
          >
            <ChevronLeft className="w-4 h-4 text-gray-500" strokeWidth={1.5} />
          </button>

          {pages.map((p, i) =>
            p === '…' ? (
              <span key={`gap-${i}`} className="text-gray-400 text-sm px-1">…</span>
            ) : (
              <button
                key={p}
                onClick={() => handlePageChange(p as number)}
                className={`w-9 h-9 rounded-xl text-sm font-semibold transition-colors ${
                  p === page
                    ? 'bg-[#e91e63] text-white shadow-sm'
                    : 'border border-gray-200 text-gray-500 hover:bg-gray-100'
                }`}
              >
                {p}
              </button>
            )
          )}

          <button
            onClick={() => handlePageChange(page + 1)}
            disabled={page >= lastPage}
            className="p-2 rounded-xl border border-gray-200 hover:bg-gray-100 transition-colors disabled:opacity-40"
          >
            <ChevronRight className="w-4 h-4 text-gray-500" strokeWidth={1.5} />
          </button>
        </div>
      )}

      {/* Modal formulaire */}
      {modal !== null && (
        <ProductFormModal
          produit={modal === 'create' ? null : modal}
          categories={categories}
          onClose={() => setModal(null)}
          onSuccess={handleModalSuccess}
        />
      )}

      {/* Confirmation suppression */}
      {deleteTarget && (
        <DeleteDialog
          nom={deleteTarget.nom}
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
