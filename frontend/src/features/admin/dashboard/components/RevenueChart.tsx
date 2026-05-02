import type { DashboardTrendPoint } from '../dashboard.types'

type RevenueChartProps = {
  data: DashboardTrendPoint[]
}

function RevenueChart({ data }: RevenueChartProps) {
  const max = Math.max(...data.map((point) => point.value), 1)

  return (
    <section className="rounded-[22px] border border-[#f0edf0] bg-white p-5 shadow-[0_18px_45px_-35px_rgba(15,23,42,0.35)] xl:col-span-2">
      <div className="mb-6 flex items-center justify-between gap-4">
        <h2 className="text-xl font-extrabold">Chiffre d'affaires</h2>
        <button className="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">
          Cette semaine
        </button>
      </div>
      <div className="flex h-64 items-end gap-4 border-b border-l border-gray-100 px-2 pb-4">
        {data.map((point) => {
          const height = Math.max((point.value / max) * 100, 12)

          return (
            <div key={point.label} className="flex flex-1 flex-col items-center gap-3">
              <div className="flex h-52 w-full items-end justify-center">
                <div
                  className="w-full max-w-14 rounded-t-2xl bg-gradient-to-t from-[#f51b7a] to-[#ffd1e3]"
                  style={{ height: `${height}%` }}
                />
              </div>
              <span className="text-xs font-semibold text-gray-600">{point.label}</span>
            </div>
          )
        })}
      </div>
    </section>
  )
}

export default RevenueChart
