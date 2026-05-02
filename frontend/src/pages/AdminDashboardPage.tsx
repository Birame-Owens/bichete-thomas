import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import DashboardLayout from '../layouts/DashboardLayout'
import { getDashboardStats } from '../services/dashboardService'
import { getToken } from '../lib/authStorage'
import type { DashboardStats } from '../types/dashboard'

function StatCard({
  label,
  value,
  pending,
}: {
  label: string
  value?: number | string | null
  pending?: boolean
}) {
  return (
    <div
      className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"
      aria-label={label}
      role="region"
    >
      <p className="text-xs font-semibold uppercase tracking-[0.3em] text-gray-400">{label}</p>
      {pending ? (
        <p className="mt-2 text-sm italic text-gray-400">Module à venir</p>
      ) : (
        <p className="mt-2 text-3xl font-bold text-gray-900" aria-live="polite">
          {value ?? '—'}
        </p>
      )}
    </div>
  )
}

function SectionHeader({ title, pending }: { title: string; pending?: boolean }) {
  return (
    <div className="flex items-center gap-3">
      <h2 className="font-display text-lg font-semibold text-gray-800">{title}</h2>
      {pending && (
        <span className="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
          Module à venir
        </span>
      )}
    </div>
  )
}

function PendingSection({ title }: { title: string }) {
  return (
    <div className="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-6">
      <SectionHeader title={title} pending />
      <p className="mt-3 text-sm text-gray-500">
        Cette section sera disponible une fois le module correspondant développé.
      </p>
    </div>
  )
}

function AdminDashboardPage() {
  const navigate = useNavigate()
  const [stats, setStats] = useState<DashboardStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const token = getToken()
    if (!token) {
      navigate('/login', { replace: true })
      return
    }

    getDashboardStats(token)
      .then(setStats)
      .catch(() => setError('Impossible de charger les statistiques du tableau de bord.'))
      .finally(() => setLoading(false))
  }, [navigate])

  const formatDate = (iso: string) => {
    const d = new Date(iso)
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
  }

  return (
    <DashboardLayout
      title="Tableau de bord"
      subtitle="Vue globale de l'activité du salon."
    >
      {loading && (
        <p className="py-10 text-center text-sm text-gray-500">Chargement…</p>
      )}

      {error && (
        <p className="rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700">
          {error}
        </p>
      )}

      {stats && (
        <div className="space-y-8">
          {/* KPI cards */}
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <StatCard label="Chiffre d'affaires" pending />
            <StatCard label="Réservations aujourd'hui" pending />
            <StatCard label="Clients" value={stats.total_clients} />
            <StatCard label="Coiffeuses actives" value={stats.total_coiffeuses_actives} />
            <StatCard label="Coiffures actives" value={stats.total_coiffures_actives} />
          </div>

          {/* Two-column grid for detailed sections */}
          <div className="grid gap-6 lg:grid-cols-2">
            {/* Clients récents */}
            <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
              <SectionHeader title="Clients récents" />
              {stats.clients_recents.length === 0 ? (
                <p className="mt-4 text-sm text-gray-400">Aucun client enregistré.</p>
              ) : (
                <table className="mt-4 w-full text-sm">
                  <thead>
                    <tr className="border-b border-gray-100 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                      <th className="pb-2 pr-4">Nom</th>
                      <th className="pb-2 pr-4">Téléphone</th>
                      <th className="pb-2">Source</th>
                      <th className="pb-2 text-right">Date</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {stats.clients_recents.map((client) => (
                      <tr key={client.id}>
                        <td className="py-2 pr-4 font-medium text-gray-900">
                          {client.prenom} {client.nom}
                        </td>
                        <td className="py-2 pr-4 text-gray-600">{client.telephone}</td>
                        <td className="py-2">
                          <span
                            className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                              client.source === 'en_ligne'
                                ? 'bg-blue-50 text-blue-700'
                                : 'bg-green-50 text-green-700'
                            }`}
                          >
                            {client.source === 'en_ligne' ? 'En ligne' : 'Physique'}
                          </span>
                        </td>
                        <td className="py-2 text-right text-gray-400">
                          {formatDate(client.created_at)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>

            {/* Coiffeuses actives */}
            <div className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
              <SectionHeader title="Coiffeuses actives" />
              {stats.coiffeuses_actives.length === 0 ? (
                <p className="mt-4 text-sm text-gray-400">Aucune coiffeuse active.</p>
              ) : (
                <table className="mt-4 w-full text-sm">
                  <thead>
                    <tr className="border-b border-gray-100 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                      <th className="pb-2 pr-4">Nom</th>
                      <th className="pb-2 pr-4">Téléphone</th>
                      <th className="pb-2 text-right">Commission</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-50">
                    {stats.coiffeuses_actives.map((c) => (
                      <tr key={c.id}>
                        <td className="py-2 pr-4 font-medium text-gray-900">
                          {c.prenom} {c.nom}
                        </td>
                        <td className="py-2 pr-4 text-gray-600">{c.telephone ?? '—'}</td>
                        <td className="py-2 text-right text-gray-600">
                          {c.pourcentage_commission}%
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>

          {/* Pending module sections */}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <PendingSection title="Paiements récents" />
            <PendingSection title="Coiffures les plus demandées" />
            <PendingSection title="Coiffeuses les plus productives" />
            <PendingSection title="Dépenses récentes" />
          </div>
        </div>
      )}
    </DashboardLayout>
  )
}

export default AdminDashboardPage
