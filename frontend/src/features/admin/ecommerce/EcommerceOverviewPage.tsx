import { useEffect, useState } from 'react'
import {
  TrendingUp, TrendingDown, ShoppingCart, Users, Wallet,
  Activity, RefreshCw, AlertTriangle, Package, Clock, BarChart3,
} from 'lucide-react'
import { EcommerceLayout } from './components/EcommerceLayout'
import { getCommandesStats, getTopProduits, getCommandes, getProduits } from './ecommerce.api'
import type { KPIStats, Produit, Commande } from './ecommerce.types'

// ─── Helpers ──────────────────────────────────────────────────────────────────

const fmtMoney = (n: number) => `${Math.round(n).toLocaleString('fr-FR')} F`

const STATUT_LABELS: Record<string, string> = {
  en_attente: 'En attente',
  confirmee: 'Confirmée',
  en_preparation: 'En préparation',
  en_production: 'En production',
  prete: 'Prête',
  en_livraison: 'En livraison',
  livree: 'Livrée',
  annulee: 'Annulée',
  echoue: 'Échouée',
  retournee: 'Retournée',
}

const STATUT_BADGES: Record<string, string> = {
  en_attente: 'bg-amber-100 text-amber-700',
  confirmee: 'bg-sky-100 text-sky-700',
  en_preparation: 'bg-violet-100 text-violet-700',
  en_production: 'bg-violet-100 text-violet-700',
  prete: 'bg-teal-100 text-teal-700',
  en_livraison: 'bg-sky-100 text-sky-700',
  livree: 'bg-green-100 text-green-700',
  annulee: 'bg-gray-100 text-gray-600',
  echoue: 'bg-rose-100 text-rose-600',
  retournee: 'bg-rose-100 text-rose-600',
}

function Skeleton({ className = '' }: { className?: string }) {
  return <div className={`bg-gray-100 rounded-lg animate-pulse ${className}`} />
}

// ─── Stat Card ────────────────────────────────────────────────────────────────

function StatCard({ title, value, sub, subPositive, icon: Icon, iconBg, iconColor, loading }: {
  title: string
  value: string | number
  sub?: string
  subPositive?: boolean
  icon: React.ElementType
  iconBg: string
  iconColor: string
  loading: boolean
}) {
  return (
    <div className="bg-white rounded-2xl p-5 border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
      <div className="flex items-start justify-between mb-3">
        <div className={`w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 ${iconBg}`}>
          <Icon className={`w-5 h-5 ${iconColor}`} strokeWidth={1.5} />
        </div>
        {sub && !loading && (
          <div className={`flex items-center gap-1 text-xs font-medium ${subPositive ? 'text-green-600' : 'text-amber-600'}`}>
            {subPositive ? <TrendingUp className="w-3 h-3" /> : <TrendingDown className="w-3 h-3" />}
            <span>{sub}</span>
          </div>
        )}
      </div>

      {loading
        ? <div className="h-7 w-28 bg-gray-100 rounded-lg animate-pulse mb-1" />
        : <p className="text-2xl font-bold text-gray-900 truncate">{value}</p>
      }

      <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-widest mt-1.5">{title}</p>
    </div>
  )
}

// ─── Order Status Row ─────────────────────────────────────────────────────────

function OrderRow({ label, count, total, dotColor, barColor }: {
  label: string
  count: number
  total: number
  dotColor: string
  barColor: string
}) {
  const pct = total > 0 ? Math.round((count / total) * 100) : 0
  return (
    <div className="py-2.5">
      <div className="flex items-center justify-between mb-1.5">
        <div className="flex items-center gap-2.5">
          <div className={`w-2 h-2 rounded-full flex-shrink-0 ${dotColor}`} />
          <span className="text-sm text-gray-500">{label}</span>
        </div>
        <span className="text-sm font-semibold text-gray-900">{count}</span>
      </div>
      <div className="h-1.5 bg-gray-100 rounded-full overflow-hidden">
        <div
          className={`h-full rounded-full transition-all duration-500 ${barColor}`}
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  )
}

// ─── Dashboard ────────────────────────────────────────────────────────────────

export default function EcommerceOverviewPage() {
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [stats, setStats] = useState<KPIStats | null>(null)
  const [topProduits, setTopProduits] = useState<Produit[]>([])
  const [dernieresCommandes, setDernieresCommandes] = useState<Commande[]>([])
  const [produitsVisibles, setProduitsVisibles] = useState(0)

  const today = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
  const todayLabel = today.charAt(0).toUpperCase() + today.slice(1)

  const loadData = async () => {
    setLoading(true)
    setError(null)
    try {
      const [statsData, top, commandes, visibles] = await Promise.all([
        getCommandesStats(),
        getTopProduits(5),
        getCommandes(1, 5),
        getProduits(1, 1, '', '', 'visible'),
      ])
      setStats(statsData)
      setTopProduits(top)
      setDernieresCommandes(commandes.data)
      setProduitsVisibles(visibles.total)
    } catch {
      setError('Impossible de charger les données du tableau de bord.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { loadData() }, [])

  // ── Erreur ──
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
              className="px-5 py-2.5 bg-[#e91e63] text-white text-xs font-semibold rounded-xl hover:bg-[#d81b60] transition-colors shadow-sm"
            >
              Réessayer
            </button>
          </div>
        </div>
      </EcommerceLayout>
    )
  }

  const parStatut: Record<string, number> = Array.isArray(stats?.commandes_par_statut)
    ? {}
    : (stats?.commandes_par_statut ?? {})

  const totalStatuts = Object.values(parStatut).reduce((a, b) => a + b, 0)

  const evolution = stats?.evolution_mensuelle ?? []
  const maxCA = Math.max(...evolution.map(e => e.chiffre_affaires), 1)

  return (
    <EcommerceLayout>
      {/* ── Header ── */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Tableau de bord boutique</h1>
          <p className="text-sm text-gray-500 mt-1">Voici un aperçu de votre boutique aujourd'hui.</p>
        </div>

        <div className="flex items-center gap-2">
          <span className="hidden md:inline-block text-xs font-medium text-gray-500 bg-white border border-gray-200 rounded-xl px-3 py-2 whitespace-nowrap">
            {todayLabel}
          </span>
          <button
            onClick={loadData}
            disabled={loading}
            className="flex items-center gap-2 px-3 py-2 text-xs font-medium text-gray-500 bg-white border border-gray-200 rounded-xl hover:bg-gray-100 hover:text-gray-900 transition-colors disabled:opacity-40"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
            <span className="hidden sm:inline">Actualiser</span>
          </button>
        </div>
      </div>

      {/* ── Stat Cards ── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <StatCard
          title="CA ce mois"
          value={loading ? '—' : fmtMoney(stats?.ca_ce_mois ?? 0)}
          sub={stats?.ca_total ? `${fmtMoney(stats.ca_total)} au total` : undefined}
          subPositive
          icon={Wallet}
          iconBg="bg-pink-50"
          iconColor="text-[#e91e63]"
          loading={loading}
        />
        <StatCard
          title="Commandes ce mois"
          value={loading ? '—' : (stats?.commandes_ce_mois ?? 0)}
          sub={stats?.commandes_en_attente ? `${stats.commandes_en_attente} en attente` : undefined}
          subPositive={false}
          icon={ShoppingCart}
          iconBg="bg-sky-50"
          iconColor="text-sky-500"
          loading={loading}
        />
        <StatCard
          title="Panier moyen"
          value={loading ? '—' : fmtMoney(stats?.panier_moyen ?? 0)}
          icon={Activity}
          iconBg="bg-violet-50"
          iconColor="text-violet-500"
          loading={loading}
        />
        <StatCard
          title="Produits en ligne"
          value={loading ? '—' : produitsVisibles}
          sub={stats?.commandes_en_retard ? `${stats.commandes_en_retard} cmd en retard` : undefined}
          subPositive={false}
          icon={Package}
          iconBg="bg-green-100"
          iconColor="text-green-600"
          loading={loading}
        />
      </div>

      {/* ── Contenu principal ── */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {/* Top produits */}
        <div className="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
          <div className="flex items-center justify-between mb-5">
            <h2 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
              <TrendingUp className="w-4 h-4 text-[#e91e63]" strokeWidth={1.5} />
              Top Produits
            </h2>
            {!loading && (
              <span className="text-[11px] font-semibold text-[#e91e63] bg-pink-50 px-2.5 py-1 rounded-full">
                {topProduits.length} produits
              </span>
            )}
          </div>

          <div className="space-y-1">
            {loading ? (
              [1, 2, 3, 4].map(i => (
                <div key={i} className="flex items-center gap-3 px-3 py-2.5">
                  <Skeleton className="w-9 h-9 rounded-xl flex-shrink-0" />
                  <div className="flex-1">
                    <Skeleton className="h-3.5 w-36 mb-1.5" />
                    <Skeleton className="h-3 w-20" />
                  </div>
                  <Skeleton className="h-4 w-16" />
                </div>
              ))
            ) : topProduits.length ? (
              topProduits.map(p => (
                <div key={p.id} className="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                  <div className="w-9 h-9 bg-gray-100 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden">
                    {p.image_principale
                      ? <img src={p.image_principale} alt={p.nom} className="w-full h-full object-cover" />
                      : <Package className="w-4 h-4 text-gray-500" strokeWidth={1.5} />
                    }
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">{p.nom}</p>
                    <p className="text-xs text-gray-500 truncate">{p.categorie?.nom ?? '—'}</p>
                  </div>
                  <div className="text-right flex-shrink-0">
                    <p className="text-sm font-semibold text-green-600">
                      {p.nombre_ventes ?? 0} vente{(p.nombre_ventes ?? 0) > 1 ? 's' : ''}
                    </p>
                    <p className="text-xs text-gray-500">{fmtMoney(Number(p.prix_actuel ?? p.prix))}</p>
                  </div>
                </div>
              ))
            ) : (
              <p className="text-sm text-gray-500 text-center py-10">Aucune vente récente</p>
            )}
          </div>
        </div>

        {/* État des commandes */}
        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
          <h2 className="text-sm font-semibold text-gray-900 flex items-center gap-2 mb-5">
            <Clock className="w-4 h-4 text-[#e91e63]" strokeWidth={1.5} />
            État des commandes
          </h2>

          {loading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4].map(i => (
                <div key={i} className="space-y-2">
                  <div className="flex justify-between">
                    <Skeleton className="h-3.5 w-28" />
                    <Skeleton className="h-3.5 w-6" />
                  </div>
                  <Skeleton className="h-1.5 w-full rounded-full" />
                </div>
              ))}
            </div>
          ) : totalStatuts > 0 ? (
            <div className="space-y-1">
              <OrderRow label="En attente" count={parStatut['en_attente'] ?? 0} total={totalStatuts} dotColor="bg-amber-400" barColor="bg-amber-400" />
              <OrderRow label="Confirmées" count={parStatut['confirmee'] ?? 0} total={totalStatuts} dotColor="bg-sky-400" barColor="bg-sky-400" />
              <OrderRow label="En préparation" count={(parStatut['en_preparation'] ?? 0) + (parStatut['en_production'] ?? 0)} total={totalStatuts} dotColor="bg-violet-400" barColor="bg-violet-400" />
              <OrderRow label="En livraison" count={(parStatut['prete'] ?? 0) + (parStatut['en_livraison'] ?? 0)} total={totalStatuts} dotColor="bg-teal-400" barColor="bg-teal-400" />
              <OrderRow label="Livrées" count={parStatut['livree'] ?? 0} total={totalStatuts} dotColor="bg-green-500" barColor="bg-green-500" />
            </div>
          ) : (
            <p className="text-sm text-gray-500 text-center py-8">Aucune commande pour le moment</p>
          )}

          {!loading && totalStatuts > 0 && (
            <div className="mt-5 pt-4 border-t border-gray-200 flex items-center justify-between">
              <span className="text-xs text-gray-500">Total</span>
              <span className="text-sm font-bold text-gray-900">{totalStatuts} commandes</span>
            </div>
          )}
        </div>
      </div>

      {/* ── Dernières commandes + Évolution ── */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {/* Dernières commandes */}
        <div className="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
          <h2 className="text-sm font-semibold text-gray-900 flex items-center gap-2 mb-5">
            <Activity className="w-4 h-4 text-[#e91e63]" strokeWidth={1.5} />
            Dernières commandes
          </h2>

          {loading ? (
            <div className="space-y-3">
              {[1, 2, 3].map(i => (
                <div key={i} className="flex items-center gap-3 px-3 py-2">
                  <Skeleton className="w-9 h-9 rounded-full flex-shrink-0" />
                  <div className="flex-1">
                    <Skeleton className="h-3.5 w-52 mb-1.5" />
                    <Skeleton className="h-3 w-36" />
                  </div>
                </div>
              ))}
            </div>
          ) : dernieresCommandes.length ? (
            <div className="space-y-1">
              {dernieresCommandes.map(c => (
                <div key={c.id} className="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                  <div className="w-9 h-9 rounded-full bg-sky-50 flex items-center justify-center flex-shrink-0">
                    <ShoppingCart className="w-4 h-4 text-sky-500" strokeWidth={1.5} />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">
                      {c.numero_commande} — {c.nom_destinataire}
                    </p>
                    <p className="text-xs text-gray-500 truncate">
                      {c.created_at ? new Date(c.created_at).toLocaleDateString('fr-FR') : c.date_commande} · {fmtMoney(Number(c.montant_total))}
                    </p>
                  </div>
                  <span className={`px-2.5 py-1 rounded-full text-[11px] font-semibold flex-shrink-0 ${STATUT_BADGES[c.statut] ?? 'bg-gray-100 text-gray-600'}`}>
                    {STATUT_LABELS[c.statut] ?? c.statut}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-500 text-center py-8">Aucune activité récente</p>
          )}
        </div>

        {/* Évolution 6 mois + top clients */}
        <div className="space-y-6">
          {/* Mini bar chart CA */}
          <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h2 className="text-sm font-semibold text-gray-900 flex items-center gap-2 mb-5">
              <BarChart3 className="w-4 h-4 text-[#e91e63]" strokeWidth={1.5} />
              CA — 6 derniers mois
            </h2>
            {loading ? (
              <Skeleton className="h-24 w-full rounded-xl" />
            ) : (
              <div className="flex items-end justify-between gap-1.5 h-24">
                {evolution.map(e => (
                  <div key={e.mois} className="flex-1 flex flex-col items-center gap-1.5 min-w-0">
                    <div
                      className="w-full rounded-t-md bg-[#e91e63]/70 hover:bg-[#e91e63] transition-colors"
                      style={{ height: `${Math.max(4, Math.round((e.chiffre_affaires / maxCA) * 80))}px` }}
                      title={`${e.mois} : ${fmtMoney(e.chiffre_affaires)} (${e.commandes} cmd)`}
                    />
                    <span className="text-[9px] text-gray-400 truncate">{e.mois.split(' ')[0]}</span>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Top clients du mois */}
          <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h2 className="text-sm font-semibold text-gray-900 flex items-center gap-2 mb-5">
              <Users className="w-4 h-4 text-[#e91e63]" strokeWidth={1.5} />
              Top clients du mois
            </h2>
            {loading ? (
              <div className="space-y-3">
                {[1, 2].map(i => <Skeleton key={i} className="h-8 w-full" />)}
              </div>
            ) : stats?.top_clients_mois?.length ? (
              <div className="space-y-2">
                {stats.top_clients_mois.map(c => (
                  <div key={c.id} className="flex items-center justify-between px-3 py-2 rounded-xl hover:bg-gray-50 transition-colors">
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-gray-900 truncate">{c.prenom} {c.nom}</p>
                      <p className="text-xs text-gray-500">{c.paiements_count} paiement{c.paiements_count > 1 ? 's' : ''}</p>
                    </div>
                    <span className="text-sm font-semibold text-green-600 flex-shrink-0">{fmtMoney(Number(c.total_paye))}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-gray-500 text-center py-4">Aucun paiement ce mois</p>
            )}
          </div>
        </div>
      </div>
    </EcommerceLayout>
  )
}
