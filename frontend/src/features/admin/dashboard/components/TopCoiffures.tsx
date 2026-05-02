import type { TopCoiffure } from '../dashboard.types'

type TopCoiffuresProps = {
  items: TopCoiffure[]
}

function TopCoiffures({ items }: TopCoiffuresProps) {
  const gradient = `conic-gradient(${items
    .reduce<string[]>((segments, item, index) => {
      const start = items.slice(0, index).reduce((sum, entry) => sum + entry.percent, 0)
      const end = start + item.percent
      segments.push(`${item.color} ${start}% ${end}%`)
      return segments
    }, [])
    .join(', ')})`

  return (
    <section className="rounded-[22px] border border-[#f0edf0] bg-white p-5 shadow-[0_18px_45px_-35px_rgba(15,23,42,0.35)]">
      <div className="mb-6 flex items-center justify-between">
        <h2 className="text-xl font-extrabold">Top coiffures reservees</h2>
        <button className="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold">
          Ce mois
        </button>
      </div>
      <div className="grid gap-6 md:grid-cols-[170px_1fr]">
        <div className="mx-auto h-40 w-40 rounded-full p-8" style={{ background: gradient }}>
          <div className="h-full w-full rounded-full bg-white" />
        </div>
        <div className="space-y-3">
          {items.map((item) => (
            <div key={item.name} className="flex items-center justify-between gap-3 text-sm">
              <span className="flex items-center gap-2">
                <span
                  className="h-3 w-3 rounded-full"
                  style={{ backgroundColor: item.color }}
                />
                {item.name}
              </span>
              <span className="font-bold">{item.percent}%</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

export default TopCoiffures
