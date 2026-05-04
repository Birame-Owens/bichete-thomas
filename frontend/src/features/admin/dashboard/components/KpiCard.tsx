type KpiCardProps = {
  label: string
  value: string
  trend?: string | null
  icon: string
  accent: string
  unavailable?: boolean
}

function KpiCard({ label, value, trend, icon, accent, unavailable = false }: KpiCardProps) {
  const stroke = unavailable ? '#f4c9da' : accent

  return (
    <article className="h-[116px] rounded-[10px] border border-[#f3edf1] bg-white px-4 py-3 shadow-[0_15px_30px_-25px_rgba(20,20,43,0.45)]">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-xs font-bold leading-none" style={{ color: accent }}>
            {label}
          </p>
          <p className="mt-4 text-[21px] font-extrabold leading-none text-[#111018]">
            {value}
          </p>
          {trend ? (
            <p className={`mt-3 text-[12px] font-semibold ${unavailable ? 'text-[#9b174f]' : 'text-emerald-600'}`}>
              {trend}
            </p>
          ) : (
            <div className="mt-3 h-[15px]" />
          )}
        </div>
        <div
          className="flex h-11 w-11 items-center justify-center rounded-full text-base font-extrabold"
          style={{ backgroundColor: `${accent}18`, color: accent }}
        >
          {icon}
        </div>
      </div>
      <div className="mt-[-8px] flex justify-end">
        <svg width="92" height="34" viewBox="0 0 92 34" aria-hidden="true">
          <path
            d="M4 25 L16 25 L25 18 L36 24 L47 16 L59 19 L70 9 L83 14 L90 5"
            fill="none"
            stroke={stroke}
            strokeWidth="3"
            strokeLinecap="round"
            strokeLinejoin="round"
            opacity={unavailable ? 0.35 : 1}
          />
          <path
            d="M4 25 L16 25 L25 18 L36 24 L47 16 L59 19 L70 9 L83 14 L90 5 L90 34 L4 34 Z"
            fill={stroke}
            opacity={unavailable ? 0.04 : 0.08}
          />
          {[4, 16, 25, 36, 47, 59, 70, 83, 90].map((x, index) => {
            const y = [25, 25, 18, 24, 16, 19, 9, 14, 5][index]

            return (
              <circle
                key={x}
                cx={x}
                cy={y}
                r="2.4"
                fill={stroke}
                opacity={unavailable ? 0.35 : 1}
              />
            )
          })}
        </svg>
      </div>
    </article>
  )
}

export default KpiCard
