import { describe, expect, it } from 'vitest'
import {
  closedDaysLabel,
  dayKeyFromDate,
  depositAmount,
  discountAmount,
  formatCurrency,
  formatDuration,
  formatShortDate,
  isClosedDate,
  promoText,
} from './client.helpers'
import type { ClientPromotion, ClientSettings } from './client.types'

// Tests unitaires sur les fonctions pures de client.helpers.ts.
// Ces helpers sont utilises par ClientHomePage et CoiffureCard. Un bug
// ici se voit immediatement en prod sur tous les ecrans -> ROI tres eleve.
//
// Note : Intl.NumberFormat utilise un narrow no-break space (U+202F) sur
// Node 23+ et un espace normal sur Node <= 22 pour le separateur de
// milliers. On normalise tous les espaces avant comparaison pour rester
// version-agnostique.
const normalizeSpaces = (s: string) => s.replace(/\s+/g, ' ')

describe('formatCurrency', () => {
  it('formate un montant simple en FCFA par defaut', () => {
    expect(normalizeSpaces(formatCurrency(15000))).toBe('15 000 FCFA')
  })

  it('accepte une autre devise', () => {
    expect(normalizeSpaces(formatCurrency(1000, 'EUR'))).toBe('1 000 EUR')
  })

  it('gere les valeurs zero / vides comme 0', () => {
    expect(normalizeSpaces(formatCurrency(0))).toBe('0 FCFA')
    expect(normalizeSpaces(formatCurrency(''))).toBe('0 FCFA')
  })

  it('accepte un montant en string', () => {
    expect(normalizeSpaces(formatCurrency('5000'))).toBe('5 000 FCFA')
  })
})

describe('formatDuration', () => {
  it('affiche les minutes seules pour < 60 min', () => {
    expect(formatDuration(30)).toBe('30 min')
    expect(formatDuration(45)).toBe('45 min')
  })

  it('affiche les heures pleines sans reste', () => {
    expect(formatDuration(60)).toBe('1h')
    expect(formatDuration(120)).toBe('2h')
  })

  it('affiche heures + minutes pour les durees mixtes', () => {
    expect(formatDuration(90)).toBe('1h 30min')
    expect(formatDuration(135)).toBe('2h 15min')
  })
})

describe('formatShortDate', () => {
  it('renvoie un tiret pour valeur vide ou null', () => {
    expect(formatShortDate(undefined)).toBe('-')
    expect(formatShortDate(null)).toBe('-')
    expect(formatShortDate('')).toBe('-')
  })

  it('renvoie la valeur originale si non parsable', () => {
    expect(formatShortDate('pas-une-date')).toBe('pas-une-date')
  })

  it('formate une date ISO en format court fr', () => {
    const formatted = formatShortDate('2026-01-01')
    expect(formatted).toMatch(/01/)
    expect(formatted.toLowerCase()).toContain('janv')
  })
})

describe('promoText', () => {
  it('formate un pourcentage avec le signe', () => {
    const promo = { id: 1, code: 'TEST', type_reduction: 'pourcentage', valeur: 15 } as unknown as ClientPromotion
    expect(promoText(promo)).toBe('-15%')
  })

  it('formate un montant fixe en devise', () => {
    const promo = { id: 1, code: 'TEST', type_reduction: 'fixe', valeur: 5000 } as unknown as ClientPromotion
    expect(normalizeSpaces(promoText(promo))).toBe('-5 000 FCFA')
  })
})

describe('discountAmount', () => {
  it('retourne 0 si aucun promo', () => {
    expect(discountAmount(null, 10000)).toBe(0)
  })

  it('calcule un pourcentage', () => {
    const promo = { type_reduction: 'pourcentage', valeur: 10 } as unknown as ClientPromotion
    expect(discountAmount(promo, 10000)).toBe(1000)
  })

  it('plafonne le pourcentage au montant total (jamais > total)', () => {
    const promo = { type_reduction: 'pourcentage', valeur: 150 } as unknown as ClientPromotion
    expect(discountAmount(promo, 10000)).toBe(10000)
  })

  it('calcule un montant fixe', () => {
    const promo = { type_reduction: 'fixe', valeur: 3000 } as unknown as ClientPromotion
    expect(discountAmount(promo, 10000)).toBe(3000)
  })

  it('plafonne le montant fixe au total (pas de reduction negative possible)', () => {
    const promo = { type_reduction: 'fixe', valeur: 50000 } as unknown as ClientPromotion
    expect(discountAmount(promo, 10000)).toBe(10000)
  })
})

describe('depositAmount', () => {
  it('retourne 0 si pas de settings', () => {
    expect(depositAmount(10000, undefined)).toBe(0)
  })

  it('utilise le pourcentage si > 0', () => {
    const settings = { pourcentage_acompte: 30, montant_acompte_defaut: 5000 } as ClientSettings
    expect(depositAmount(10000, settings)).toBe(3000)
  })

  it('retombe sur le montant fixe si pourcentage = 0', () => {
    const settings = { pourcentage_acompte: 0, montant_acompte_defaut: 5000 } as ClientSettings
    expect(depositAmount(10000, settings)).toBe(5000)
  })

  it('plafonne le montant fixe au total (pas de demande > prix de la prestation)', () => {
    const settings = { pourcentage_acompte: 0, montant_acompte_defaut: 5000 } as ClientSettings
    expect(depositAmount(2000, settings)).toBe(2000)
  })
})

describe('dayKeyFromDate', () => {
  it('retourne la cle francaise du jour de la semaine', () => {
    // 2026-05-11 = lundi
    expect(dayKeyFromDate('2026-05-11')).toBe('lundi')
    // 2026-05-17 = dimanche
    expect(dayKeyFromDate('2026-05-17')).toBe('dimanche')
  })
})

describe('isClosedDate', () => {
  it('retourne false si pas de settings ou jours_fermeture vide', () => {
    expect(isClosedDate('2026-05-17', undefined)).toBe(false)
    expect(isClosedDate('2026-05-17', { jours_fermeture: [] } as unknown as ClientSettings)).toBe(false)
  })

  it('retourne true si le jour est dans la liste fermeture', () => {
    const settings = { jours_fermeture: ['dimanche'] } as unknown as ClientSettings
    expect(isClosedDate('2026-05-17', settings)).toBe(true)
  })

  it('retourne false si le jour n est pas ferme', () => {
    const settings = { jours_fermeture: ['dimanche'] } as unknown as ClientSettings
    expect(isClosedDate('2026-05-11', settings)).toBe(false)
  })
})

describe('closedDaysLabel', () => {
  it('retourne le label "Ouvert toute la semaine" pour un tableau vide', () => {
    expect(closedDaysLabel([])).toBe('Ouvert toute la semaine')
  })

  it('liste les jours fermes en ordre semaine', () => {
    expect(closedDaysLabel(['dimanche', 'lundi'])).toBe('Ferme: Lundi, Dimanche')
  })

  it('ignore les jours invalides en silence', () => {
    expect(closedDaysLabel(['jour_invalide', 'lundi'])).toBe('Ferme: Lundi')
  })
})
