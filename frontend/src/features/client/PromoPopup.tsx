import { useEffect, useState } from 'react'
import { X } from 'lucide-react'
import { apiClient } from '../../lib/apiClient'

type PromoData = {
  id: number
  code: string
  nom: string | null
  type_reduction: 'pourcentage' | 'montant'
  valeur: number | string
}

const STORAGE_KEY = 'promo_popup_seen'

function formatDiscount(type: 'pourcentage' | 'montant', valeur: number | string) {
  const val = Number(valeur)

  if (type === 'pourcentage') {
    return `${val}% de réduction sur toutes les prestations`
  }

  return `${val.toLocaleString('fr-FR')} FCFA de réduction sur votre prochaine réservation`
}

function PromoPopup() {
  const [promo, setPromo] = useState<PromoData | null>(null)
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    if (sessionStorage.getItem(STORAGE_KEY)) return

    apiClient
      .get<{ data: PromoData | null }>('/client/promo-active')
      .then(({ data }) => {
        if (data.data) {
          setPromo(data.data)
          setVisible(true)
        }
      })
      .catch(() => {})
  }, [])

  const dismiss = () => {
    sessionStorage.setItem(STORAGE_KEY, '1')
    setVisible(false)
  }

  if (!visible || !promo) return null

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label="Offre promotionnelle"
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
      onClick={(e) => {
        if (e.target === e.currentTarget) dismiss()
      }}
    >
      <div className="relative w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl">
        <button
          type="button"
          onClick={dismiss}
          className="absolute right-3 top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-black/25 text-white transition hover:bg-black/45"
          aria-label="Fermer"
        >
          <X className="h-4 w-4" />
        </button>

        {/* En-tête gradient avec identité du salon */}
        <div className="flex h-44 flex-col items-center justify-center bg-gradient-to-br from-[#e91e63] to-[#880e4f]">
          <div className="mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-white/20 text-4xl">
            ✂️
          </div>
          <p className="text-sm font-black uppercase tracking-widest text-white/90">Salon Thomas</p>
          <p className="mt-1 text-xs font-semibold uppercase tracking-wider text-white/60">Dakar</p>
        </div>

        {/* Corps du popup */}
        <div className="px-6 py-5 text-center">
          <p className="text-xs font-black uppercase tracking-widest text-[#e91e63]">Offre Spéciale</p>

          {promo.nom && (
            <p className="mt-1 text-xl font-black text-gray-900">{promo.nom}</p>
          )}

          <p className="mt-2 text-sm font-semibold text-gray-500">
            {formatDiscount(promo.type_reduction, promo.valeur)}
          </p>

          {/* Badge code promo avec copie au clic */}
          <button
            type="button"
            onClick={() => {
              void navigator.clipboard.writeText(promo.code)
            }}
            className="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-lg bg-gray-50 px-4 py-2 transition hover:bg-gray-100"
            title="Copier le code"
          >
            <span className="text-xs font-bold text-gray-500">Code :</span>
            <span className="font-black tracking-widest text-[#e91e63]">{promo.code}</span>
          </button>

          {/* CTA principal */}
          <a
            href="/#catalogue"
            onClick={dismiss}
            className="mt-5 flex w-full items-center justify-center rounded-xl bg-gray-900 px-6 py-3 text-sm font-black text-white transition hover:bg-gray-700"
          >
            DÉCOUVRIR
          </a>

          {/* Lien de refus */}
          <button
            type="button"
            onClick={dismiss}
            className="mt-3 text-xs font-semibold text-gray-400 underline underline-offset-2 transition hover:text-gray-600"
          >
            Je ne suis pas intéressé(e)
          </button>
        </div>
      </div>
    </div>
  )
}

export default PromoPopup
