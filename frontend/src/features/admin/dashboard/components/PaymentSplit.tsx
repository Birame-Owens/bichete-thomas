import type { PaymentSplit as PaymentSplitItem } from '../dashboard.types'

type PaymentSplitProps = {
  items: PaymentSplitItem[]
}

function PaymentSplit({ items }: PaymentSplitProps) {
  return (
    <section className="rounded-[22px] border border-[#f0edf0] bg-white p-5 shadow-[0_18px_45px_-35px_rgba(15,23,42,0.35)]">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-xl font-extrabold">Repartition des paiements</h2>
        <button className="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold">
          Ce mois
        </button>
      </div>
      <div className="space-y-3">
        {items.map((item) => (
          <div key={item.methode} className="grid grid-cols-[1fr_auto_auto] items-center gap-3 text-sm">
            <span className="flex items-center gap-3 font-bold">
              <span
                className="h-9 w-9 rounded-xl"
                style={{ backgroundColor: item.color }}
              />
              {item.methode}
            </span>
            <span>{item.montant.toLocaleString('fr-FR')} FCFA</span>
            <span className="font-bold">{item.percent}%</span>
          </div>
        ))}
      </div>
    </section>
  )
}

export default PaymentSplit
