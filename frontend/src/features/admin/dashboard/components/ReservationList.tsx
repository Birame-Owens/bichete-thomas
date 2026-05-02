import type { ReservationPreview } from '../dashboard.types'

type ReservationListProps = {
  title: string
  items: ReservationPreview[]
  compact?: boolean
}

const labels = {
  confirmee: 'Confirmee',
  acompte_paye: 'Acompte paye',
  en_attente: 'En attente',
}

function statusClass(status: ReservationPreview['statut']) {
  if (status === 'confirmee') {
    return 'bg-emerald-50 text-emerald-700'
  }

  if (status === 'acompte_paye') {
    return 'bg-sky-50 text-sky-700'
  }

  return 'bg-amber-50 text-amber-700'
}

function ReservationList({ title, items, compact = false }: ReservationListProps) {
  return (
    <section className="rounded-[22px] border border-[#f0edf0] bg-white p-5 shadow-[0_18px_45px_-35px_rgba(15,23,42,0.35)]">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-xl font-extrabold">{title}</h2>
        <button className="text-sm font-bold text-[#e91e63]">Voir tout</button>
      </div>
      <div className="space-y-3">
        {items.map((item) => (
          <div
            key={item.id}
            className="grid items-center gap-3 rounded-2xl border border-gray-100 px-3 py-3 sm:grid-cols-[1fr_auto]"
          >
            <div>
              <p className="font-bold">{item.client}</p>
              <p className="text-sm text-gray-500">{item.coiffure}</p>
              {!compact && (
                <span className={`mt-2 inline-flex rounded-full px-3 py-1 text-xs font-bold ${statusClass(item.statut)}`}>
                  {labels[item.statut]}
                </span>
              )}
            </div>
            <div className="text-left sm:text-right">
              <p className="text-sm font-bold">{item.heure}</p>
              <p className="text-sm text-gray-500">{item.montant.toLocaleString('fr-FR')} FCFA</p>
            </div>
          </div>
        ))}
      </div>
    </section>
  )
}

export default ReservationList
