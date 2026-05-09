import { memo } from 'react'
import { Star } from 'lucide-react'

type RatingStarsProps = {
  value: number
  size?: 'xs' | 'sm'
}

// Etoiles d evaluation. memo() : le composant est utilise dans la liste des
// avis du modal (potentiellement >10 occurrences) ; sans memo, chaque
// frappe dans le formulaire reservation re-rendrait toutes les etoiles.
function RatingStarsBase({ value, size = 'sm' }: RatingStarsProps) {
  const iconSize = size === 'xs' ? 'h-3.5 w-3.5' : 'h-4 w-4'

  return (
    <span className="inline-flex items-center gap-0.5 text-[#f59e0b]">
      {Array.from({ length: 5 }, (_, index) => (
        <Star
          key={index}
          className={`${iconSize} ${index < Math.round(value) ? 'fill-[#f59e0b]' : 'fill-none text-slate-300'}`}
        />
      ))}
    </span>
  )
}

export const RatingStars = memo(RatingStarsBase)
