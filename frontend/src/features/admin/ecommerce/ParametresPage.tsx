import { useEffect, useState } from 'react'
import { Plus, Truck, Trash2, Edit2, ToggleLeft, ToggleRight, X, AlertTriangle, CheckCircle2, MapPin } from 'lucide-react'
import { EcommerceLayout } from './components/EcommerceLayout'
import {
  getDeliveryZones, createDeliveryZone, updateDeliveryZone,
  deleteDeliveryZone, toggleDeliveryZoneStatus,
} from './ecommerce.api'
import { money } from './components/EcommerceUi'
import type { DeliveryZone } from './ecommerce.types'

interface ToastItem { id: number; message: string; type: 'success' | 'error' }

interface ZoneForm {
  nom: string
  prix: string
  ordre_affichage: string
  est_active: boolean
}

const emptyForm: ZoneForm = { nom: '', prix: '', ordre_affichage: '0', est_active: true }

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

export function ParametresPage() {
  const [zones, setZones] = useState<DeliveryZone[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<DeliveryZone | null>(null)
  const [form, setForm] = useState<ZoneForm>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<DeliveryZone | null>(null)
  const [toasts, setToasts] = useState<ToastItem[]>([])

  const addToast = (message: string, type: 'success' | 'error' = 'success') => {
    const id = Date.now()
    setToasts(prev => [...prev, { id, message, type }])
    setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), 3500)
  }
  const removeToast = (id: number) => setToasts(prev => prev.filter(t => t.id !== id))

  const load = async () => {
    setLoading(true)
    try {
      setZones(await getDeliveryZones())
    } catch {
      addToast('Impossible de charger les zones de livraison.', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  const openCreate = () => {
    setEditing(null)
    setForm(emptyForm)
    setError(null)
    setModalOpen(true)
  }

  const openEdit = (zone: DeliveryZone) => {
    setEditing(zone)
    setForm({
      nom: zone.nom,
      prix: String(zone.prix),
      ordre_affichage: String(zone.ordre_affichage),
      est_active: zone.est_active,
    })
    setError(null)
    setModalOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.nom.trim()) { setError('Le nom de la zone est obligatoire.'); return }
    setSaving(true)
    setError(null)
    try {
      const payload = {
        nom: form.nom.trim(),
        prix: Number(form.prix) || 0,
        ordre_affichage: Number(form.ordre_affichage) || 0,
        est_active: form.est_active,
      }
      if (editing) {
        await updateDeliveryZone(editing.id, payload)
        addToast('Zone modifiée.')
      } else {
        await createDeliveryZone(payload)
        addToast('Zone créée.')
      }
      setModalOpen(false)
      load()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      setError(msg ?? 'Enregistrement impossible.')
    } finally {
      setSaving(false)
    }
  }

  const handleToggle = async (zone: DeliveryZone) => {
    try {
      await toggleDeliveryZoneStatus(zone.id)
      setZones(prev => prev.map(z => z.id === zone.id ? { ...z, est_active: !z.est_active } : z))
    } catch {
      addToast('Impossible de changer le statut.', 'error')
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    try {
      await deleteDeliveryZone(deleteTarget.id)
      addToast(`Zone « ${deleteTarget.nom} » supprimée.`)
      setDeleteTarget(null)
      load()
    } catch {
      addToast('Impossible de supprimer la zone.', 'error')
    }
  }

  const inputCls = 'w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5] transition-all'

  return (
    <EcommerceLayout>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Paramètres de la boutique</h1>
          <p className="text-sm text-gray-500 mt-1">Gérez les zones et frais de livraison de votre boutique.</p>
        </div>
      </div>

      {/* Zones de livraison */}
      <section className="bg-white rounded-2xl border border-gray-200 shadow-sm">
        <div className="flex items-center justify-between p-5 border-b border-gray-100">
          <div className="flex items-center gap-2.5">
            <div className="w-9 h-9 rounded-xl bg-pink-50 flex items-center justify-center">
              <Truck className="w-4.5 h-4.5 text-[#e91e63]" strokeWidth={1.5} />
            </div>
            <div>
              <h2 className="text-sm font-bold text-gray-900">Zones de livraison</h2>
              <p className="text-xs text-gray-500">Chaque zone définit un tarif de livraison à domicile</p>
            </div>
          </div>
          <button onClick={openCreate} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#e91e63] text-white text-sm font-semibold hover:bg-[#d81b60] transition-colors">
            <Plus className="w-3.5 h-3.5" strokeWidth={2.5} />
            Ajouter une zone
          </button>
        </div>

        <div className="p-5">
          {loading ? (
            <div className="space-y-2.5">
              {[1, 2, 3].map(i => <div key={i} className="h-14 bg-gray-100 rounded-xl animate-pulse" />)}
            </div>
          ) : zones.length === 0 ? (
            <div className="py-10 text-center">
              <MapPin className="w-8 h-8 text-gray-300 mx-auto mb-3" strokeWidth={1.5} />
              <p className="text-sm font-medium text-gray-900 mb-1">Aucune zone de livraison</p>
              <p className="text-xs text-gray-500 mb-4">Ajoutez vos quartiers/villes avec leur tarif (ex : Dakar centre 2 000, Banlieue 3 000…)</p>
              <button onClick={openCreate} className="px-4 py-2 bg-[#e91e63] text-white text-xs font-semibold rounded-xl hover:bg-[#d81b60] transition-colors">
                + Première zone
              </button>
            </div>
          ) : (
            <div className="space-y-2">
              {zones.map(zone => (
                <div key={zone.id} className="flex items-center gap-4 px-4 py-3 rounded-xl border border-gray-100 hover:bg-gray-50 transition-colors group">
                  <MapPin className={`w-4 h-4 flex-shrink-0 ${zone.est_active ? 'text-[#e91e63]' : 'text-gray-300'}`} strokeWidth={1.5} />
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-gray-900 truncate">{zone.nom}</p>
                  </div>
                  <span className="text-sm font-bold text-gray-900">{money(zone.prix)}</span>
                  <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold ${
                    zone.est_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                  }`}>
                    <span className={`w-1.5 h-1.5 rounded-full ${zone.est_active ? 'bg-green-500' : 'bg-gray-400'}`} />
                    {zone.est_active ? 'Active' : 'Masquée'}
                  </span>
                  <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onClick={() => handleToggle(zone)} className="p-2 rounded-lg hover:bg-gray-100" title={zone.est_active ? 'Désactiver' : 'Activer'}>
                      {zone.est_active
                        ? <ToggleRight className="w-4 h-4 text-gray-600" strokeWidth={1.5} />
                        : <ToggleLeft className="w-4 h-4 text-gray-400" strokeWidth={1.5} />}
                    </button>
                    <button onClick={() => openEdit(zone)} className="p-2 rounded-lg hover:bg-gray-100">
                      <Edit2 className="w-4 h-4 text-gray-600" strokeWidth={1.5} />
                    </button>
                    <button onClick={() => setDeleteTarget(zone)} className="p-2 rounded-lg hover:bg-rose-50">
                      <Trash2 className="w-4 h-4 text-rose-400" strokeWidth={1.5} />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}

          <p className="mt-4 text-xs text-gray-400">
            💡 Le retrait au salon reste toujours gratuit. Seule la livraison à domicile utilise ces tarifs.
          </p>
        </div>
      </section>

      {/* Modal créer/éditer */}
      {modalOpen && (
        <div className="fixed inset-0 bg-black/30 backdrop-blur-[2px] z-50 flex items-center justify-center p-4" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-3xl border border-gray-200 shadow-xl w-full max-w-md">
            <div className="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-200">
              <h2 className="font-bold text-gray-900 text-lg">{editing ? 'Modifier la zone' : 'Nouvelle zone de livraison'}</h2>
              <button onClick={() => setModalOpen(false)} className="p-2 rounded-xl hover:bg-gray-100">
                <X className="w-4 h-4 text-gray-500" strokeWidth={1.5} />
              </button>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Nom de la zone <span className="text-rose-400">*</span></label>
                <input type="text" value={form.nom} onChange={e => setForm({ ...form, nom: e.target.value })} placeholder="Ex : Dakar centre, Parcelles, Rufisque…" className={inputCls} />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Frais (FCFA)</label>
                  <input type="number" min="0" step="100" value={form.prix} onChange={e => setForm({ ...form, prix: e.target.value })} placeholder="2000" className={inputCls} />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Ordre</label>
                  <input type="number" min="0" value={form.ordre_affichage} onChange={e => setForm({ ...form, ordre_affichage: e.target.value })} className={inputCls} />
                </div>
              </div>
              <label className="flex items-center justify-between px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
                <span className="text-sm font-medium text-gray-900">Zone active (proposée aux clients)</span>
                <div onClick={() => setForm({ ...form, est_active: !form.est_active })} className={`w-10 h-6 rounded-full transition-colors relative ${form.est_active ? 'bg-[#e91e63]' : 'bg-gray-300'}`}>
                  <span className={`absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${form.est_active ? 'translate-x-5' : 'translate-x-1'}`} />
                </div>
              </label>

              {error && (
                <div className="flex items-center gap-2.5 px-4 py-3 bg-rose-50 border border-rose-200 rounded-xl">
                  <AlertTriangle className="w-4 h-4 text-rose-500 flex-shrink-0" strokeWidth={1.5} />
                  <p className="text-sm text-rose-600">{error}</p>
                </div>
              )}

              <div className="flex gap-3 pt-2">
                <button type="button" onClick={() => setModalOpen(false)} className="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-500 hover:bg-gray-100 transition-colors">Annuler</button>
                <button type="submit" disabled={saving} className="flex-1 py-3 rounded-xl bg-[#e91e63] text-white text-sm font-semibold hover:bg-[#d81b60] transition-colors disabled:opacity-50">
                  {saving ? 'Enregistrement…' : editing ? 'Enregistrer' : 'Créer'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Confirmation suppression */}
      {deleteTarget && (
        <div className="fixed inset-0 bg-black/30 backdrop-blur-[2px] z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl border border-gray-200 shadow-xl w-full max-w-sm p-6">
            <div className="w-12 h-12 bg-rose-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <Trash2 className="w-5 h-5 text-rose-500" strokeWidth={1.5} />
            </div>
            <h3 className="font-bold text-gray-900 text-center mb-1">Supprimer la zone ?</h3>
            <p className="text-sm text-gray-500 text-center mb-5">« {deleteTarget.nom} » sera supprimée. Les commandes déjà passées ne sont pas affectées.</p>
            <div className="flex gap-3">
              <button onClick={() => setDeleteTarget(null)} className="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-500 hover:bg-gray-100 transition-colors">Annuler</button>
              <button onClick={handleDelete} className="flex-1 py-2.5 rounded-xl bg-rose-500 text-white text-sm font-semibold hover:bg-rose-600 transition-colors">Supprimer</button>
            </div>
          </div>
        </div>
      )}

      {/* Toasts */}
      <div className="fixed bottom-6 right-6 flex flex-col gap-2 z-[200] min-w-[280px] max-w-[360px]">
        {toasts.map(t => <Toast key={t.id} message={t.message} type={t.type} onDismiss={() => removeToast(t.id)} />)}
      </div>
    </EcommerceLayout>
  )
}
