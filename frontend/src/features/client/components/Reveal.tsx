import { useEffect, useRef, useState, type ElementType, type ReactNode } from 'react'

type RevealProps = {
  children: ReactNode
  /** Balise HTML rendue (div par defaut). Permet <Reveal as="section">. */
  as?: ElementType
  /** Delai d apparition en ms pour creer un effet de cascade entre elements. */
  delay?: number
  className?: string
}

/**
 * Enveloppe un bloc et le fait apparaitre (fondu + glissement vers le haut)
 * quand il entre dans le viewport, via IntersectionObserver (zero dependance,
 * tres leger - critique pour la cible mobile/3G).
 *
 * L animation reelle est portee par les classes CSS .bt-reveal / .is-visible
 * (cf. index.css) qui se desactivent automatiquement si l utilisateur a
 * "reduire les animations" active. On revele une seule fois (unobserve) pour
 * ne pas re-animer au scroll arriere.
 */
function Reveal({ children, as, delay = 0, className = '' }: RevealProps) {
  const Tag = (as ?? 'div') as ElementType
  const ref = useRef<HTMLElement | null>(null)
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const node = ref.current
    if (!node) {
      return
    }

    // Si IntersectionObserver indisponible (très vieux navigateur), on affiche
    // directement le contenu pour ne jamais bloquer la lecture.
    if (typeof IntersectionObserver === 'undefined') {
      setVisible(true)
      return
    }

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            setVisible(true)
            observer.unobserve(entry.target)
          }
        }
      },
      { threshold: 0.15, rootMargin: '0px 0px -8% 0px' },
    )

    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  return (
    <Tag
      ref={ref}
      className={`bt-reveal ${visible ? 'is-visible' : ''} ${className}`.trim()}
      style={delay ? { transitionDelay: `${delay}ms` } : undefined}
    >
      {children}
    </Tag>
  )
}

export default Reveal
