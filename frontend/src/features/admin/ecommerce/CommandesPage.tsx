import { useEffect, useMemo, useRef, useState } from 'react'
import {
  Search, Filter, Package, Clock, CheckCircle2, XCircle, Truck, CreditCard,
  ChevronLeft, ChevronRight, Eye, RefreshCw, Calendar, MapPin, Trash2, X, Loader2,
} from 'lucide-react'
import { EcommerceLayout } from './components/EcommerceLayout'
import {
  getCommandes, getCommandeById, updateCommandeStatus, markCommandeAsPaid, deleteCommande, getCommandesStats,
} from './ecommerce.api'
import type { Commande, CommandeStatut, KPIStats, LaravelPaginated } from './ecommerce.types'

// ─── Constantes ────────────────────────────────────────────────────────────────

const statuts: CommandeStatut[] = [
  'en_attente', 'confirmee', 'en_preparation', 'en_production',
  'prete', 'en_livraison', 'livree', 'annulee', 'echoue', 'retournee',
]

const statutLabels: Record<CommandeStatut, string> = {
  en_attente: 'En attente', confirmee: 'Confirmée', en_preparation: 'En préparation',
  en_production: 'En production', prete: 'Prête', en_livraison: 'En livraison',
  livree: 'Livrée', annulee: 'Annulée', echoue: 'Échouée', retournee: 'Retournée',
}

const methodesPaiement = [
  { value: 'especes', label: 'Espèces' },
  { value: 'wave', label: 'Wave' },
  { value: 'orange_money', label: 'Orange Money' },
  { value: 'free_money', label: 'Free Money' },
  { value: 'virement', label: 'Virement' },
  { value: 'cheque', label: 'Chèque' },
  { value: 'carte', label: 'Carte' },
]

const fmtMoney = (n: number | null | undefined) => (Number(n) || 0).toLocaleString('fr-FR')

const statutBadge = (statut: string) => {
  switch (statut) {
    case 'livree': return 'bg-green-100 text-green-700'
    case 'en_livraison':
    case 'prete': return 'bg-sky-100 text-sky-700'
    case 'en_preparation':
    case 'en_production': return 'bg-violet-100 text-violet-700'
    case 'confirmee': return 'bg-teal-100 text-teal-700'
    case 'annulee':
    case 'echoue':
    case 'retournee': return 'bg-rose-100 text-rose-600'
    default: return 'bg-amber-100 text-amber-700'
  }
}

const paymentBadge = (paid: boolean | undefined) =>
  paid ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'

interface ToastItem { id: number; message: string; type: 'success' | 'error' }

// ─── StatCard ───────────────────────────────────────────────────────────────

function StatCard({ title, value, icon: Icon, iconBg, iconColor, loading }: {
  title: string; value: string; icon: React.ElementType; iconBg: string; iconColor: string; loading: boolean
}) {
  return (
    <div className="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm">
      <div className={`w-10 h-10 rounded-xl flex items-center justify-center mb-3 ${iconBg}`}>
        <Icon className={`w-5 h-5 ${iconColor}`} strokeWidth={1.5} />
      </div>
      {loading
        ? <div className="h-7 w-20 bg-gray-100 rounded-lg animate-pulse mb-1" />
        : <p className="text-2xl font-bold text-gray-900">{value}</p>}
      <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-widest mt-1">{title}</p>
    </div>
  )
}

function Toast({ message, type, onDismiss }: { message: string; type: 'success' | 'error'; onDismiss: () => void }) {
  return (
    <div className={`flex items-center gap-3 px-4 py-3 rounded-2xl shadow-lg border text-sm font-medium
      ${type === 'success' ? 'bg-white border-gray-200 text-gray-900' : 'bg-rose-50 border-rose-200 text-rose-700'}`}>
      {type === 'success'
        ? <CheckCircle2 className="w-4 h-4 text-green-500 flex-shrink-0" strokeWidth={1.5} />
        : <XCircle className="w-4 h-4 text-rose-500 flex-shrink-0" strokeWidth={1.5} />}
      <span className="flex-1">{message}</span>
      <button onClick={onDismiss} className="ml-1 p-0.5 rounded hover:opacity-60"><X className="w-3 h-3" strokeWidth={2} /></button>
    </div>
  )
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export function CommandesPage() {
  const [items, setItems] = useState<LaravelPaginated<Commande> | null>(null)
  const [stats, setStats] = useState<KPIStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [statsLoading, setStatsLoading] = useState(true)
  const [page, setPage] = useState(1)

  const [numero, setNumero] = useState('')
  const [clientSearch, setClientSearch] = useState('')
  const [statutFilter, setStatutFilter] = useState('')
  const [paymentFilter, setPaymentFilter] = useState('')
  const [prioriteFilter, setPrioriteFilter] = useState('')
  const [dateDebut, setDateDebut] = useState('')
  const [dateFin, setDateFin] = useState('')

  const [selected, setSelected] = useState<Commande | null>(null)
  const [detailOpen, setDetailOpen] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)
  const [newStatut, setNewStatut] = useState<CommandeStatut>('confirmee')
  const [saving, setSaving] = useState(false)

  const [payTarget, setPayTarget] = useState<Commande | null>(null)
  const [payMontant, setPayMontant] = useState('')
  const [payMethode, setPayMethode] = useState('wave')
  const [payRef, setPayRef] = useState('')
  const [payLoading, setPayLoading] = useState(false)

  const [deleteTarget, setDeleteTarget] = useState<Commande | null>(null)
  const [toasts, setToasts] = useState<ToastItem[]>([])
  const filtersReady = useRef(false)

  const addToast = (message: string, type: ToastItem['type'] = 'success') => {
    const id = Date.now() + Math.floor(Math.random() * 1000)
    setToasts(prev => [...prev, { id, message, type }])
    setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), 3500)
  }
  const removeToast = (id: number) => setToasts(prev => prev.filter(t => t.id !== id))

  const loadStats = async () => {
    setStatsLoading(true)
    try {
      setStats(await getCommandesStats())
    } catch { /* non bloquant */ }
    finally { setStatsLoading(false) }
  }

  const loadCommandes = async (p = page) => {
    setLoading(true)
    try {
      const data = await getCommandes(p, 12, numero, statutFilter, dateDebut, dateFin, prioriteFilter, clientSearch)
      setItems(data)
    } catch {
      addToast('Impossible de charger les commandes.', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadCommandes(1)
    loadStats()
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!filtersReady.current) { filtersReady.current = true; return }
    const t = setTimeout(() => { setPage(1); loadCommandes(1) }, 350)
    return () => clearTimeout(t)
  }, [numero, clientSearch, statutFilter, prioriteFilter, dateDebut, dateFin]) // eslint-disable-line react-hooks/exhaustive-deps

  const goToPage = (p: number) => { setPage(p); loadCommandes(p) }

  const openDetail = async (id: number) => {
    setDetailOpen(true)
    setDetailLoading(true)
    setSelected(null)
    try {
      const c = await getCommandeById(id)
      setSelected(c)
      setNewStatut(c.statut)
    } catch {
      addToast('Impossible de charger la commande.', 'error')
      setDetailOpen(false)
    } finally {
      setDetailLoading(false)
    }
  }

  const handleUpdateStatus = async () => {
    if (!selected) return
    setSaving(true)
    try {
      await updateCommandeStatus(selected.id, newStatut)
      addToast('Statut mis à jour.')
      setSelected({ ...selected, statut: newStatut, statut_label: statutLabels[newStatut] })
      loadCommandes()
      loadStats()
    } catch {
      addToast('Mise à jour impossible.', 'error')
    } finally {
      setSaving(false)
    }
  }

  const openPayment = (commande: Commande) => {
    setPayTarget(commande)
    setPayMontant(String(Math.round(Number(commande.montant_total) || 0)))
    setPayMethode('wave')
    setPayRef('')
  }

  const handlePayment = async () => {
    if (!payTarget) return
    setPayLoading(true)
    try {
      await markCommandeAsPaid(payTarget.id, {
        montant: Number(payMontant) || 0,
        methode_paiement: payMethode,
        reference_paiement: payRef.trim() || null,
      })
      addToast(`Paiement de ${fmtMoney(Number(payMontant))} FCFA enregistré.`)
      setPayTarget(null)
      loadCommandes()
      loadStats()
      if (detailOpen && selected?.id === payTarget.id) openDetail(payTarget.id)
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      addToast(msg ?? 'Paiement impossible.', 'error')
    } finally {
      setPayLoading(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    try {
      await deleteCommande(deleteTarget.id)
      addToast(`Commande ${deleteTarget.numero_commande} supprimée.`)
      setDeleteTarget(null)
      loadCommandes()
      loadStats()
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
      addToast(msg ?? 'Suppression impossible.', 'error')
    }
  }

  // Filtre paiement appliqué côté client (le backend ne le gère pas)
  const commandes = useMemo(() => {
    const list = items?.data ?? []
    if (!paymentFilter) return list
    return list.filter(c => c.est_payee === (paymentFilter === 'paid'))
  }, [items, paymentFilter])

  const lastPage = items?.last_page ?? 1
  const pages = useMemo(() => {
    const out: Array<number | '…'> = []
    if (lastPage <= 6) { for (let i = 1; i <= lastPage; i++) out.push(i); return out }
    const start = Math.max(1, page - 1), end = Math.min(lastPage, page + 1)
    if (start > 1) out.push(1, '…')
    for (let i = start; i <= end; i++) out.push(i)
    if (end < lastPage) out.push('…', lastPage)
    return out
  }, [lastPage, page])

  const parStatut = (stats && !Array.isArray(stats.commandes_par_statut)) ? stats.commandes_par_statut : {}
  const countLivrees = parStatut['livree'] ?? 0
  const countAnnulees = (parStatut['annulee'] ?? 0) + (parStatut['echoue'] ?? 0)

  const inputCls = 'w-full pl-10 pr-4 py-2.5 text-sm bg-white border border-gray-200 rounded-xl text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5] transition-all'
  const selectCls = 'px-3 py-2.5 rounded-xl text-xs font-semibold bg-white border border-gray-200 text-gray-600 focus:outline-none focus:border-[#ff5ca5]'

  return (
    <EcommerceLayout>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Gestion des commandes</h1>
          <p className="text-sm text-gray-500 mt-1">Suivez, gérez et traitez les commandes de votre boutique.</p>
        </div>
        <button
          onClick={() => { setStatutFilter('en_attente') }}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#e91e63] text-white text-sm font-semibold hover:bg-[#d81b60] transition-colors"
        >
          <Clock className="w-3.5 h-3.5" strokeWidth={2} />
          Commandes en attente
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <StatCard title="Total" value={stats ? String(stats.total_commandes) : '—'} icon={Package} iconBg="bg-gray-100" iconColor="text-gray-600" loading={statsLoading} />
        <StatCard title="En attente" value={stats ? String(stats.commandes_en_attente) : '—'} icon={Clock} iconBg="bg-amber-100" iconColor="text-amber-700" loading={statsLoading} />
        <StatCard title="Livrées" value={stats ? String(countLivrees) : '—'} icon={Truck} iconBg="bg-green-100" iconColor="text-green-600" loading={statsLoading} />
        <StatCard title="Revenus" value={stats ? `${fmtMoney(stats.ca_total)} F` : '—'} icon={CreditCard} iconBg="bg-pink-50" iconColor="text-[#e91e63]" loading={statsLoading} />
        <StatCard title="Annulées" value={stats ? String(countAnnulees) : '—'} icon={XCircle} iconBg="bg-rose-100" iconColor="text-rose-500" loading={statsLoading} />
      </div>

      {/* Filtres */}
      <div className="bg-white border border-gray-200 rounded-2xl p-4 lg:p-5 mb-6 shadow-sm">
        <div className="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-widest mb-4">
          <Filter className="w-3.5 h-3.5" strokeWidth={1.5} />
          Filtres & recherche
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
          <div className="relative">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" strokeWidth={1.5} />
            <input value={numero} onChange={e => setNumero(e.target.value)} placeholder="Numéro commande…" className={inputCls} />
          </div>
          <div className="relative">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" strokeWidth={1.5} />
            <input value={clientSearch} onChange={e => setClientSearch(e.target.value)} placeholder="Client, téléphone…" className={inputCls} />
          </div>
          <select value={statutFilter} onChange={e => setStatutFilter(e.target.value)} className={selectCls}>
            <option value="">Tous les statuts</option>
            {statuts.map(s => <option key={s} value={s}>{statutLabels[s]}</option>)}
          </select>
          <select value={paymentFilter} onChange={e => setPaymentFilter(e.target.value)} className={selectCls}>
            <option value="">Paiement (tous)</option>
            <option value="paid">Payée</option>
            <option value="unpaid">En attente</option>
          </select>
          <select value={prioriteFilter} onChange={e => setPrioriteFilter(e.target.value)} className={selectCls}>
            <option value="">Priorité (toutes)</option>
            <option value="normale">Normale</option>
            <option value="urgente">Urgente</option>
            <option value="tres_urgente">Très urgente</option>
          </select>
          <div className="relative">
            <Calendar className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" strokeWidth={1.5} />
            <input type="date" value={dateDebut} onChange={e => setDateDebut(e.target.value)} className={inputCls} />
          </div>
          <div className="relative">
            <Calendar className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" strokeWidth={1.5} />
            <input type="date" value={dateFin} onChange={e => setDateFin(e.target.value)} className={inputCls} />
          </div>
          <button
            onClick={() => { loadCommandes(); loadStats() }}
            disabled={loading}
            className="inline-flex items-center justify-center gap-2 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-600 hover:bg-gray-100 transition-colors disabled:opacity-40"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} strokeWidth={1.5} />
            Actualiser
          </button>
        </div>
        <p className="text-xs text-gray-400 mt-3">{loading ? 'Chargement…' : `${commandes.length} commande(s) affichée(s)`}</p>
      </div>

      {/* Tableau desktop */}
      <div className="hidden lg:block bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
        <div className="grid grid-cols-[1.3fr_1.7fr_0.8fr_1.1fr_1fr_1.1fr_1.1fr_1fr] gap-4 px-5 py-3 text-[11px] font-semibold text-gray-500 uppercase tracking-widest border-b border-gray-100 bg-gray-50">
          <div>Numéro</div><div>Client</div><div>Articles</div><div>Montant</div>
          <div>Paiement</div><div>Statut</div><div>Date</div><div className="text-right">Actions</div>
        </div>
        {loading ? (
          <div className="p-6 space-y-3">{Array.from({ length: 6 }).map((_, i) => <div key={i} className="h-12 bg-gray-100 rounded-xl animate-pulse" />)}</div>
        ) : commandes.length === 0 ? (
          <div className="p-12 text-center">
            <Package className="w-8 h-8 text-gray-300 mx-auto mb-3" strokeWidth={1.5} />
            <p className="text-sm font-medium text-gray-900 mb-1">Aucune commande</p>
            <p className="text-xs text-gray-500">Ajustez vos filtres ou réessayez plus tard.</p>
          </div>
        ) : commandes.map(order => (
          <div key={order.id} className="grid grid-cols-[1.3fr_1.7fr_0.8fr_1.1fr_1fr_1.1fr_1.1fr_1fr] gap-4 px-5 py-4 border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors items-center">
            <div>
              <p className="text-sm font-semibold text-gray-900">{order.numero_commande}</p>
              {order.est_en_retard && <span className="text-[11px] text-rose-600 font-semibold">En retard</span>}
            </div>
            <div className="min-w-0">
              <p className="text-sm font-semibold text-gray-900 truncate">{order.client?.nom_complet ?? order.nom_destinataire}</p>
              <p className="text-xs text-gray-500 truncate">{order.client?.telephone ?? order.telephone_livraison}</p>
            </div>
            <div className="text-xs text-gray-500">{order.nb_articles ?? 0} art.</div>
            <div className="text-sm font-semibold text-gray-900">{fmtMoney(order.montant_total)} F</div>
            <div><span className={`px-2.5 py-1 rounded-full text-[11px] font-semibold ${paymentBadge(order.est_payee)}`}>{order.est_payee ? 'Payée' : 'En attente'}</span></div>
            <div><span className={`px-2.5 py-1 rounded-full text-[11px] font-semibold ${statutBadge(order.statut)}`}>{order.statut_label ?? statutLabels[order.statut]}</span></div>
            <div className="text-xs text-gray-500">{order.date_commande}</div>
            <div className="flex items-center justify-end gap-1.5">
              <button onClick={() => openDetail(order.id)} className="p-2 rounded-xl border border-gray-200 hover:bg-gray-100 transition-colors" title="Détails">
                <Eye className="w-3.5 h-3.5 text-gray-600" strokeWidth={1.5} />
              </button>
              {!order.est_payee && (
                <button onClick={() => openPayment(order)} className="p-2 rounded-xl border border-gray-200 hover:bg-green-50 transition-colors" title="Enregistrer un paiement">
                  <CreditCard className="w-3.5 h-3.5 text-green-600" strokeWidth={1.5} />
                </button>
              )}
              <button
                onClick={() => setDeleteTarget(order)}
                disabled={order.statut !== 'en_attente' || order.peut_supprimer === false}
                className="p-2 rounded-xl border border-rose-200 hover:bg-rose-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                title="Supprimer"
              >
                <Trash2 className="w-3.5 h-3.5 text-rose-500" strokeWidth={1.5} />
              </button>
            </div>
          </div>
        ))}
      </div>

      {/* Cartes mobile */}
      <div className="lg:hidden space-y-4">
        {loading ? Array.from({ length: 4 }).map((_, i) => <div key={i} className="h-32 bg-gray-100 rounded-2xl animate-pulse" />)
          : commandes.map(order => (
            <div key={order.id} className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-sm font-semibold text-gray-900">{order.numero_commande}</p>
                  <p className="text-xs text-gray-500">{order.date_commande}</p>
                </div>
                <span className={`px-2.5 py-1 rounded-full text-[11px] font-semibold ${statutBadge(order.statut)}`}>{order.statut_label ?? statutLabels[order.statut]}</span>
              </div>
              <p className="mt-3 text-sm font-semibold text-gray-900">{order.client?.nom_complet ?? order.nom_destinataire}</p>
              <p className="text-xs text-gray-500">{order.client?.telephone ?? order.telephone_livraison}</p>
              <div className="mt-3 flex items-center justify-between">
                <span className="text-sm font-semibold text-gray-900">{fmtMoney(order.montant_total)} F</span>
                <span className={`px-2.5 py-1 rounded-full text-[11px] font-semibold ${paymentBadge(order.est_payee)}`}>{order.est_payee ? 'Payée' : 'En attente'}</span>
              </div>
              <button onClick={() => openDetail(order.id)} className="mt-3 w-full px-3 py-2 rounded-xl bg-[#e91e63] text-white text-xs font-semibold hover:bg-[#d81b60] transition-colors">Voir détails</button>
            </div>
          ))}
      </div>

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2 mt-8">
          <button onClick={() => goToPage(page - 1)} disabled={page <= 1} className="p-2 rounded-xl border border-gray-200 hover:bg-gray-100 transition-colors disabled:opacity-40">
            <ChevronLeft className="w-4 h-4 text-gray-500" strokeWidth={1.5} />
          </button>
          {pages.map((p, i) => p === '…'
            ? <span key={`g-${i}`} className="text-gray-400 text-sm px-1">…</span>
            : <button key={p} onClick={() => goToPage(p as number)} className={`w-9 h-9 rounded-xl text-sm font-semibold transition-colors ${p === page ? 'bg-[#e91e63] text-white' : 'border border-gray-200 text-gray-500 hover:bg-gray-100'}`}>{p}</button>)}
          <button onClick={() => goToPage(page + 1)} disabled={page >= lastPage} className="p-2 rounded-xl border border-gray-200 hover:bg-gray-100 transition-colors disabled:opacity-40">
            <ChevronRight className="w-4 h-4 text-gray-500" strokeWidth={1.5} />
          </button>
        </div>
      )}

      {/* ── Modal détail ── */}
      {detailOpen && (
        <div className="fixed inset-0 bg-black/30 backdrop-blur-[2px] z-50 flex items-center justify-center p-4" onClick={(e) => { if (e.target === e.currentTarget) setDetailOpen(false) }}>
          <div className="bg-white rounded-3xl border border-gray-200 shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div className="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-200 flex-shrink-0">
              <div>
                <h2 className="font-bold text-gray-900 text-lg">Commande {selected?.numero_commande ?? ''}</h2>
                <p className="text-xs text-gray-500 mt-0.5">{selected?.date_commande}</p>
              </div>
              <button onClick={() => setDetailOpen(false)} className="p-2 rounded-xl hover:bg-gray-100"><X className="w-4 h-4 text-gray-500" strokeWidth={1.5} /></button>
            </div>

            {detailLoading || !selected ? (
              <div className="p-16 flex items-center justify-center"><Loader2 className="w-8 h-8 text-[#e91e63] animate-spin" /></div>
            ) : (
              <div className="flex-1 overflow-y-auto p-6 space-y-6">
                {/* Résumé */}
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div><p className="text-gray-500">Montant total</p><p className="font-bold text-gray-900">{fmtMoney(selected.montant_total)} F</p></div>
                  <div><p className="text-gray-500">Paiement</p><span className={`inline-block mt-0.5 px-2.5 py-1 rounded-full text-[11px] font-semibold ${paymentBadge(selected.est_payee)}`}>{selected.est_payee ? 'Payée' : 'En attente'}</span></div>
                  <div><p className="text-gray-500">Statut</p><span className={`inline-block mt-0.5 px-2.5 py-1 rounded-full text-[11px] font-semibold ${statutBadge(selected.statut)}`}>{selected.statut_label ?? statutLabels[selected.statut]}</span></div>
                  <div><p className="text-gray-500">Source</p><p className="font-medium text-gray-900 capitalize">{selected.source ?? '—'}</p></div>
                </div>

                {/* Livraison */}
                <div className="border-t border-gray-100 pt-4">
                  <h3 className="text-sm font-bold text-gray-900 mb-2 flex items-center gap-2"><MapPin className="w-4 h-4 text-[#e91e63]" strokeWidth={1.5} />Livraison</h3>
                  <div className="text-sm text-gray-700 space-y-1">
                    <p className="font-medium">{selected.nom_destinataire} · {selected.telephone_livraison}</p>
                    <p>{selected.adresse_livraison}</p>
                    {selected.zone_livraison_nom && <p className="text-gray-500">Zone : {selected.zone_livraison_nom} — Livraison {fmtMoney(selected.frais_livraison)} F</p>}
                    {selected.instructions_livraison && <p className="text-gray-500 italic">« {selected.instructions_livraison} »</p>}
                  </div>
                </div>

                {/* Articles */}
                {selected.articles_commandes && selected.articles_commandes.length > 0 && (
                  <div className="border-t border-gray-100 pt-4">
                    <h3 className="text-sm font-bold text-gray-900 mb-2">Articles</h3>
                    <div className="space-y-1.5 text-sm">
                      {selected.articles_commandes.map(a => (
                        <div key={a.id} className="flex justify-between text-gray-700">
                          <span>{a.nom_produit} <span className="text-gray-400">×{a.quantite}</span></span>
                          <span className="font-medium">{fmtMoney(a.prix_total_article)} F</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Actions statut */}
                <div className="border-t border-gray-100 pt-4 space-y-3">
                  <div>
                    <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Changer le statut</label>
                    <select value={newStatut} onChange={e => setNewStatut(e.target.value as CommandeStatut)} className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5]">
                      {statuts.map(s => <option key={s} value={s}>{statutLabels[s]}</option>)}
                    </select>
                  </div>
                  <div className="flex gap-3">
                    <button onClick={handleUpdateStatus} disabled={saving || newStatut === selected.statut} className="flex-1 py-3 rounded-xl bg-[#e91e63] text-white text-sm font-semibold hover:bg-[#d81b60] transition-colors disabled:opacity-50">
                      {saving ? 'Mise à jour…' : 'Mettre à jour le statut'}
                    </button>
                    {!selected.est_payee && (
                      <button onClick={() => openPayment(selected)} className="flex-1 py-3 rounded-xl border border-green-500 text-green-600 text-sm font-semibold hover:bg-green-50 transition-colors">
                        Enregistrer un paiement
                      </button>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ── Modal paiement ── */}
      {payTarget && (
        <div className="fixed inset-0 bg-black/30 backdrop-blur-[2px] z-[60] flex items-center justify-center p-4" onClick={(e) => { if (e.target === e.currentTarget) setPayTarget(null) }}>
          <div className="bg-white rounded-3xl border border-gray-200 shadow-xl w-full max-w-md">
            <div className="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-200">
              <h2 className="font-bold text-gray-900 text-lg">Enregistrer un paiement</h2>
              <button onClick={() => setPayTarget(null)} className="p-2 rounded-xl hover:bg-gray-100"><X className="w-4 h-4 text-gray-500" strokeWidth={1.5} /></button>
            </div>
            <div className="p-6 space-y-4">
              <p className="text-sm text-gray-500">Commande <span className="font-semibold text-gray-900">{payTarget.numero_commande}</span> — total {fmtMoney(payTarget.montant_total)} F</p>
              <div>
                <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Montant reçu (FCFA)</label>
                <input type="number" min="0" value={payMontant} onChange={e => setPayMontant(e.target.value)} className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5]" />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Méthode</label>
                <select value={payMethode} onChange={e => setPayMethode(e.target.value)} className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5]">
                  {methodesPaiement.map(m => <option key={m.value} value={m.value}>{m.label}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Référence (optionnel)</label>
                <input value={payRef} onChange={e => setPayRef(e.target.value)} placeholder="N° transaction Wave/OM…" className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5]" />
              </div>
              <div className="flex gap-3 pt-2">
                <button onClick={() => setPayTarget(null)} className="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-500 hover:bg-gray-100 transition-colors">Annuler</button>
                <button onClick={handlePayment} disabled={payLoading} className="flex-1 py-3 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700 transition-colors disabled:opacity-50">
                  {payLoading ? 'Enregistrement…' : 'Valider le paiement'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ── Confirmation suppression ── */}
      {deleteTarget && (
        <div className="fixed inset-0 bg-black/30 backdrop-blur-[2px] z-[60] flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl border border-gray-200 shadow-xl w-full max-w-sm p-6">
            <div className="w-12 h-12 bg-rose-100 rounded-2xl flex items-center justify-center mx-auto mb-4"><Trash2 className="w-5 h-5 text-rose-500" strokeWidth={1.5} /></div>
            <h3 className="font-bold text-gray-900 text-center mb-1">Supprimer la commande ?</h3>
            <p className="text-sm text-gray-500 text-center mb-5">« {deleteTarget.numero_commande} » sera supprimée définitivement.</p>
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
