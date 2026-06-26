import { useEffect, useState } from 'react'
import { Package, ShoppingCart, TrendingUp, AlertCircle } from 'lucide-react'
import { EcommerceLayout } from './components/EcommerceLayout'
import { Panel, ErrorState } from './components/EcommerceUi'
import { getCommandesStats } from './ecommerce.api'
import type { KPIStats } from './ecommerce.types'

export function EcommerceOverviewPage() {
  const [stats, setStats] = useState<KPIStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    loadStats()
  }, [])

  async function loadStats() {
    try {
      setLoading(true)
      setError(null)
      const data = await getCommandesStats()
      setStats(data)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors du chargement des statistiques')
    } finally {
      setLoading(false)
    }
  }

  return (
    <EcommerceLayout>
      <Panel title="Tableau de bord Ecommerce" subtitle="Suivi des ventes boutique en ligne">
        {error && <ErrorState message={error} />}

        {loading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {[...Array(4)].map((_, i) => (
              <div key={i} className="h-32 bg-gray-200 rounded-lg animate-pulse" />
            ))}
          </div>
        ) : stats ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <KPICard
              icon={TrendingUp}
              label="Chiffre d'affaires"
              value={`${(stats.chiffre_affaires / 1000).toFixed(0)}k FCFA`}
              color="text-green-600"
              bgColor="bg-green-50"
            />
            <KPICard
              icon={ShoppingCart}
              label="Nombre de commandes"
              value={stats.nombre_commandes.toString()}
              color="text-blue-600"
              bgColor="bg-blue-50"
            />
            <KPICard
              icon={Package}
              label="Produits actifs"
              value={stats.nombre_produits.toString()}
              color="text-purple-600"
              bgColor="bg-purple-50"
            />
            <KPICard
              icon={AlertCircle}
              label="En attente de paiement"
              value={stats.commandes_en_attente.toString()}
              color="text-orange-600"
              bgColor="bg-orange-50"
            />
          </div>
        ) : null}
      </Panel>
    </EcommerceLayout>
  )
}

function KPICard({
  icon: Icon,
  label,
  value,
  color,
  bgColor,
}: {
  icon: any
  label: string
  value: string
  color: string
  bgColor: string
}) {
  return (
    <div className={`rounded-lg ${bgColor} p-6`}>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-gray-600">{label}</p>
          <p className={`text-2xl font-bold ${color} mt-2`}>{value}</p>
        </div>
        <Icon size={32} className={color} strokeWidth={1.5} />
      </div>
    </div>
  )
}
