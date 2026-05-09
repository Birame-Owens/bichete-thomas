import heroImage from '../../assets/hero.jpg'
import type { ClientCoiffure, ClientPromotion, ClientSettings } from './client.types'

// Helpers de formatage et calcul utilises a la fois par ClientHomePage et
// les sous-composants extraits (CoiffureCard, etc.) - factorises ici pour
// eviter la duplication et permettre le memo correct des composants enfants.

export function formatCurrency(value: number | string, devise = 'FCFA') {
  const amount = Number(value || 0)

  return `${new Intl.NumberFormat('fr-FR').format(amount)} ${devise}`
}

export function formatDuration(minutes: number) {
  if (minutes < 60) {
    return `${minutes} min`
  }

  const hours = Math.floor(minutes / 60)
  const remaining = minutes % 60

  return remaining > 0 ? `${hours}h ${remaining}min` : `${hours}h`
}

export function formatShortDate(value?: string | null) {
  if (!value) {
    return '-'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
  }).format(date)
}

export function coiffureImage(coiffure: ClientCoiffure) {
  return coiffure.image ?? coiffure.images[0]?.url ?? heroImage
}

export function promoText(promo: ClientPromotion) {
  if (promo.type_reduction === 'pourcentage') {
    return `-${Number(promo.valeur)}%`
  }

  return `-${formatCurrency(promo.valeur)}`
}

export function discountAmount(promo: ClientPromotion | null, total: number) {
  if (!promo) {
    return 0
  }

  if (promo.type_reduction === 'pourcentage') {
    return Math.min(total, total * (Number(promo.valeur) / 100))
  }

  return Math.min(total, Number(promo.valeur))
}

export function depositAmount(total: number, settings?: ClientSettings) {
  if (!settings) {
    return 0
  }

  if (Number(settings.pourcentage_acompte) > 0) {
    return total * (Number(settings.pourcentage_acompte) / 100)
  }

  return Math.min(Number(settings.montant_acompte_defaut || 0), total)
}

export const weekDays = [
  'lundi',
  'mardi',
  'mercredi',
  'jeudi',
  'vendredi',
  'samedi',
  'dimanche',
] as const

export const weekDayLabels: Record<(typeof weekDays)[number], string> = {
  lundi: 'Lundi',
  mardi: 'Mardi',
  mercredi: 'Mercredi',
  jeudi: 'Jeudi',
  vendredi: 'Vendredi',
  samedi: 'Samedi',
  dimanche: 'Dimanche',
}

export function dayKeyFromDate(dateString: string) {
  const date = new Date(`${dateString}T00:00:00`)
  const dayIndex = date.getDay()

  return weekDays[(dayIndex + 6) % 7]
}

export function isClosedDate(dateString: string, settings?: ClientSettings) {
  if (!settings?.jours_fermeture || settings.jours_fermeture.length === 0) {
    return false
  }

  const key = dayKeyFromDate(dateString)

  return settings.jours_fermeture.includes(key)
}

export function closedDaysLabel(days: string[]) {
  if (!days || days.length === 0) {
    return 'Ouvert toute la semaine'
  }

  return `Ferme: ${weekDays.filter((day) => days.includes(day)).map((day) => weekDayLabels[day]).join(', ')}`
}

export function toInputDate(date: Date) {
  const normalized = new Date(date)
  normalized.setMinutes(normalized.getMinutes() - normalized.getTimezoneOffset())

  return normalized.toISOString().slice(0, 10)
}

export function todayInput() {
  return toInputDate(new Date())
}
