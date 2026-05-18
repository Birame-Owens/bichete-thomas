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
    // Bloqué si déjà vu dans cette session (dismiss ou clic CTA)
    try {
      if (sessionStorage.getItem(STORAGE_KEY)) return
    } catch {
      // sessionStorage indisponible (mode privé strict) → on continue quand même
    }

    apiClient
      .get<{ data: PromoData | null }>('/client/promo-active')
      .then(({ data }) => {
        if (data.data) {
          setPromo(data.data)
          setVisible(true)
        }
      })
      .catch((err: unknown) => {
        // Ne pas afficher d'erreur à l'utilisateur, mais logguer pour debug
        if (import.meta.env.DEV) {
          console.error('[PromoPopup] Erreur lors du chargement du popup promo :', err)
        }
      })
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

        {/* Image en-tête du popup */}
        <div className="h-56 overflow-hidden">
          <img
            src="/popup-promo.png"
            alt="Offre spéciale Salon Thomas"
            className="h-full w-full object-cover object-top"
          />
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
