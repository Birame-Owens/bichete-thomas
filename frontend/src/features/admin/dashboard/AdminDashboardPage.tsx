import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import AdminLayout from '../../../layouts/AdminLayout'
import { clearAuth, getUser } from '../../../lib/authStorage'
import { getAdminDashboard } from './dashboard.api'
import type {
  AvailabilityValue,
  DashboardApiResponse,
  DashboardCard,
  DashboardReservation,
  PaymentMethod,
  RevenuePoint,
  SystemLog,
  TopCoiffure,
} from './dashboard.types'
import KpiCard from './components/KpiCard'

const chartColors = ['#f51b7a', '#c41468', '#8e154f', '#f07355', '#6c123e']

function money(value?: number | string | null) {
  const numeric = Number(value ?? 0)

  return `${numeric.toLocaleString('fr-FR')} FCFA`
}

function cardValue(card: DashboardCard) {
  if (!card.available) {
    return 'Indisponible'
  }

  return card.format === 'money' ? money(card.value) : Number(card.value ?? 0).toLocaleString('fr-FR')
}

function EmptyState({ message }: { message: string }) {
  return (
    <div className="flex h-full min-h-[116px] items-center justify-center rounded-xl border border-dashed border-[#efc7d8] bg-[#fff8fb] px-4 text-center text-sm font-semibold text-[#a91550]">
      {message}
    </div>
  )
}

function Card({
  title,
  action,
  children,
  className = '',
}: {
  title: string
  action?: string
  children: React.ReactNode
  className?: string
}) {
  return (
    <section className={`rounded-[10px] border border-[#f3edf1] bg-white p-5 shadow-[0_15px_30px_-25px_rgba(20,20,43,0.45)] ${className}`}>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-lg font-extrabold">{title}</h2>
        {action && <button className="text-xs font-bold text-[#e91e63]">{action}</button>}
      </div>
      {children}
    </section>
  )
}

function RevenueChart({ chart }: { chart: AvailabilityValue<RevenuePoint[]> }) {
  const points = chart.data ?? []
  const max = Math.max(...points.map((point) => point.value), 1)

  return (
    <Card title="Chiffre d'affaires">
      <div className="mb-2 flex justify-end">
        <button className="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold">
          Cette semaine
        </button>
      </div>
      {!chart.available ? (
        <EmptyState message={chart.message ?? 'Module paiements non implemente.'} />
      ) : (
        <div className="grid h-[190px] grid-cols-[42px_1fr] gap-3">
          <div className="flex flex-col justify-between text-[11px] font-semibold text-gray-500">
            <span>{money(max)}</span>
            <span>{money(max / 2)}</span>
            <span>0</span>
          </div>
          <div className="flex items-end gap-3 border-b border-l border-gray-100 px-2 pb-3">
            {points.map((point) => (
              <div key={point.date} className="flex flex-1 flex-col items-center gap-2">
                <div className="flex h-32 w-full items-end justify-center">
                  <span
                    className="w-full max-w-9 rounded-t-full bg-gradient-to-t from-[#f51b7a] to-[#ffd4e5]"
                    style={{ height: `${Math.max((point.value / max) * 100, 8)}%` }}
                  />
                </div>
                <span className="text-xs font-semibold text-gray-600">{point.label}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </Card>
  )
}

function TopCoiffures({ chart }: { chart: AvailabilityValue<TopCoiffure[]> }) {
  const items = chart.data ?? []
  const gradient = items.length
    ? `conic-gradient(${items
        .reduce<string[]>((segments, item, index) => {
          const start = items.slice(0, index).reduce((sum, entry) => sum + entry.percent, 0)
          const end = start + item.percent
          segments.push(`${chartColors[index % chartColors.length]} ${start}% ${end}%`)
          return segments
        }, [])
        .join(', ')})`
    : undefined

  return (
    <Card title="Top coiffures reservees">
      {!chart.available ? (
        <EmptyState message={chart.message ?? 'Module reservations non implemente.'} />
      ) : (
        <div className="grid gap-4 md:grid-cols-[120px_1fr]">
          <div className="h-28 w-28 rounded-full p-6" style={{ background: gradient }}>
            <div className="h-full w-full rounded-full bg-white" />
          </div>
          <div className="space-y-2">
            {items.map((item, index) => (
              <div key={item.name} className="flex items-center justify-between gap-3 text-xs">
                <span className="flex items-center gap-2">
                  <span
                    className="h-2.5 w-2.5 rounded-full"
                    style={{ backgroundColor: chartColors[index % chartColors.length] }}
                  />
                  {item.name}
                </span>
                <span className="font-bold">{item.percent}%</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </Card>
  )
}

function ReservationsToday({ list }: { list: AvailabilityValue<DashboardReservation[]> }) {
  return (
    <Card title="Reservations du jour" action="Voir tout">
      {!list.available ? (
        <EmptyState message={list.message ?? 'Module reservations non implemente.'} />
      ) : (list.data ?? []).length === 0 ? (
        <EmptyState message="Aucune reservation aujourd'hui." />
      ) : (
        <div className="space-y-3">
          {(list.data ?? []).slice(0, 4).map((reservation) => (
            <div key={reservation.id} className="rounded-xl border border-gray-100 px-3 py-2 text-xs">
              <p className="font-bold">Reservation #{reservation.id}</p>
              <p className="text-gray-500">{String(reservation.statut ?? '')}</p>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

function RecentReservations({ list }: { list: AvailabilityValue<DashboardReservation[]> }) {
  return (
    <Card title="Dernieres reservations" action="Voir tout">
      {!list.available ? (
        <EmptyState message={list.message ?? 'Module reservations non implemente.'} />
      ) : (list.data ?? []).length === 0 ? (
        <EmptyState message="Aucune reservation recente." />
      ) : (
        <div className="overflow-hidden rounded-xl border border-gray-100">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#fbf8fa] uppercase text-gray-500">
              <tr>
                <th className="px-3 py-2">Reference</th>
                <th className="px-3 py-2">Date</th>
                <th className="px-3 py-2">Statut</th>
              </tr>
            </thead>
            <tbody>
              {(list.data ?? []).slice(0, 5).map((reservation) => (
                <tr key={reservation.id} className="border-t border-gray-100">
                  <td className="px-3 py-3 font-bold">#{reservation.id}</td>
                  <td className="px-3 py-3 text-gray-600">{String(reservation.date_reservation ?? reservation.created_at ?? '-')}</td>
                  <td className="px-3 py-3 text-gray-600">{String(reservation.statut ?? '-')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  )
}

function PaymentSplit({ split }: { split: AvailabilityValue<PaymentMethod[]> }) {
  return (
    <Card title="Repartition des paiements">
      {!split.available ? (
        <EmptyState message={split.message ?? 'Module paiements non implemente.'} />
      ) : (
        <div className="space-y-3">
          {(split.data ?? []).map((item, index) => (
            <div key={item.method} className="grid grid-cols-[1fr_auto_auto] items-center gap-3 text-xs">
              <span className="flex items-center gap-2 font-bold">
                <span
                  className="h-8 w-8 rounded-lg"
                  style={{ backgroundColor: chartColors[index % chartColors.length] }}
                />
                {item.method}
              </span>
              <span>{money(item.amount)}</span>
              <span className="font-bold">{item.percent}%</span>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

function QuickPayment({ available, message }: { available: boolean; message?: string | null }) {
  return (
    <Card title="Encaissement rapide">
      <div className="space-y-3">
        <input className="w-full rounded-lg border border-gray-200 px-4 py-3 text-sm" placeholder="Nom / Prenom du client" disabled={!available} />
        <input className="w-full rounded-lg border border-gray-200 px-4 py-3 text-sm" placeholder="Montant paye" disabled={!available} />
        <select className="w-full rounded-lg border border-gray-200 px-4 py-3 text-sm" disabled={!available}>
          <option>Methode de paiement</option>
        </select>
        <button className="w-full rounded-lg bg-[#e91e63] px-5 py-3 text-sm font-bold text-white disabled:opacity-60" disabled={!available}>
          Enregistrer paiement
        </button>
      </div>
      {!available && <p className="mt-3 text-xs font-semibold text-[#a91550]">{message}</p>}
    </Card>
  )
}

function Activity({ list }: { list: AvailabilityValue<SystemLog[]> }) {
  return (
    <Card title="Activite recente" action="Voir tout" className="lg:col-span-2">
      {!list.available ? (
        <EmptyState message={list.message ?? 'Module logs systeme non implemente.'} />
      ) : (list.data ?? []).length === 0 ? (
        <EmptyState message="Aucune activite recente." />
      ) : (
        <div className="space-y-3">
          {(list.data ?? []).map((log) => (
            <div key={log.id} className="grid grid-cols-[58px_1fr] text-sm">
              <span className="font-semibold text-gray-500">
                {log.created_at
                  ? new Date(log.created_at).toLocaleTimeString('fr-FR', {
                      hour: '2-digit',
                      minute: '2-digit',
                    })
                  : '--:--'}
              </span>
              <span>{log.description ?? `${log.action}${log.module ? ` - ${log.module}` : ''}`}</span>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

function Promo({ dashboard }: { dashboard: DashboardApiResponse }) {
  const promo = dashboard.promo

  return (
    <section className="rounded-[10px] bg-[#fff0f6] p-6 text-[#b01357]">
      {promo.available && promo.data ? (
        <>
          <p className="text-base font-extrabold">{promo.data.nom ?? 'Promotion active'}</p>
          <p className="mt-3 text-sm">
            Utilisez le code : <span className="font-extrabold">{promo.data.code}</span>
          </p>
        </>
      ) : (
        <>
          <p className="text-base font-extrabold">Modules en attente</p>
          <p className="mt-3 text-sm">
            {dashboard.modules_en_attente.length > 0
              ? dashboard.modules_en_attente.join(', ')
              : promo.message ?? 'Aucune promotion active.'}
          </p>
        </>
      )}
    </section>
  )
}

function AdminDashboardPage() {
  const navigate = useNavigate()
  const user = getUser()
  const [dashboard, setDashboard] = useState<DashboardApiResponse | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    getAdminDashboard()
      .then(setDashboard)
      .catch(() => {
        clearAuth()
        navigate('/login', { replace: true })
      })
      .finally(() => setLoading(false))
  }, [navigate])

  const cards = useMemo(() => dashboard?.cards ?? [], [dashboard])

  if (loading || !dashboard) {
    return (
      <AdminLayout>
        <div className="rounded-[10px] bg-white p-8 text-sm font-bold text-gray-600">
          Chargement du dashboard...
        </div>
      </AdminLayout>
    )
  }

  return (
    <AdminLayout>
      <header className="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h1 className="text-[25px] font-extrabold tracking-tight">
            Bonjour, {user?.name ?? 'Administratrice'}
          </h1>
          <p className="mt-2 text-sm text-gray-500">
            Voici un apercu general de votre salon aujourd'hui.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <button className="flex h-9 w-9 items-center justify-center rounded-full bg-white text-sm shadow-sm">
            S
          </button>
          <button className="flex h-9 w-9 items-center justify-center rounded-full bg-white text-sm shadow-sm">
            N
          </button>
          <button className="rounded-lg bg-white px-4 py-2 text-sm font-bold shadow-sm">
            {dashboard.period.label ?? dashboard.period.today}
          </button>
        </div>
      </header>

      <section className="grid gap-4 lg:grid-cols-4">
        {cards.map((card) => (
          <KpiCard
            key={card.key}
            label={card.label}
            value={cardValue(card)}
            trend={card.trend}
            icon={card.icon}
            accent={card.color}
            unavailable={!card.available}
          />
        ))}
      </section>

      <section className="mt-4 grid items-stretch gap-4 lg:grid-cols-[1.55fr_1fr_0.9fr]">
        <RevenueChart chart={dashboard.charts.chiffre_affaires} />
        <TopCoiffures chart={dashboard.charts.top_coiffures} />
        <ReservationsToday list={dashboard.lists.reservations_du_jour} />
      </section>

      <section className="mt-4 grid items-stretch gap-4 lg:grid-cols-[1.55fr_1fr_0.9fr]">
        <RecentReservations list={dashboard.lists.dernieres_reservations} />
        <PaymentSplit split={dashboard.payments.repartition} />
        <QuickPayment
          available={dashboard.quick_payment.available}
          message={dashboard.quick_payment.message}
        />
      </section>

      <section className="mt-4 grid items-stretch gap-4 lg:grid-cols-[1.55fr_1fr_0.9fr]">
        <Activity list={dashboard.lists.activite_recente} />
        <Promo dashboard={dashboard} />
      </section>
    </AdminLayout>
  )
}

export default AdminDashboardPage
