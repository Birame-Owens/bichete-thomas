import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react'
import {
  BadgePercent,
  CalendarDays,
  Clock3,
  MessageCircle,
  RefreshCw,
  Save,
  ShieldAlert,
  WalletCards,
} from 'lucide-react'
import AdminLayout from '../../../layouts/AdminLayout'
import { getSystemSettings, updateSystemSetting } from './settings.api'
import type { ReservationSettingsForm, SystemSetting } from './settings.types'

const inputClass =
  'w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-900 outline-none transition focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10'

const primaryButtonClass =
  'rounded-lg bg-[#e91e63] px-4 py-2.5 text-sm font-black text-white shadow-[0_14px_24px_-18px_rgba(233,30,99,0.9)] transition hover:bg-[#c41468] disabled:cursor-not-allowed disabled:opacity-60'

const secondaryButtonClass =
  'rounded-lg border border-gray-200 px-3 py-2 text-sm font-black text-gray-600 transition hover:border-[#e91e63] hover:text-[#c41468] disabled:cursor-not-allowed disabled:opacity-50'

const settingKeys = [
  'montant_acompte_defaut',
  'pourcentage_acompte',
  'heure_ouverture',
  'heure_fermeture',
  'telephone_whatsapp',
  'devise',
  'delai_annulation_heures',
  'seuil_retard_minutes',
  'seuil_absence_minutes',
  'limite_reservations_par_jour',
  'limite_reservations_par_creneau',
] as const

const emptyForm: ReservationSettingsForm = {
  montant_acompte_defaut: '5000',
  pourcentage_acompte: '30',
  heure_ouverture: '09:00',
  heure_fermeture: '19:00',
  telephone_whatsapp: '+221 77 000 00 00',
  devise: 'FCFA',
  delai_annulation_heures: '24',
  seuil_retard_minutes: '15',
  seuil_absence_minutes: '30',
  limite_reservations_par_jour: '15',
  limite_reservations_par_creneau: '3',
}

type SettingKey = (typeof settingKeys)[number]

function rawSettingValue(setting?: SystemSetting) {
  const value = setting?.valeur?.value

  return value === null || value === undefined ? '' : String(value)
}

function buildSettingsForm(items: Record<string, SystemSetting>): ReservationSettingsForm {
  return {
    montant_acompte_defaut: rawSettingValue(items.montant_acompte_defaut) || emptyForm.montant_acompte_defaut,
    pourcentage_acompte: rawSettingValue(items.pourcentage_acompte) || emptyForm.pourcentage_acompte,
    heure_ouverture: rawSettingValue(items.heure_ouverture) || emptyForm.heure_ouverture,
    heure_fermeture: rawSettingValue(items.heure_fermeture) || emptyForm.heure_fermeture,
    telephone_whatsapp: rawSettingValue(items.telephone_whatsapp) || emptyForm.telephone_whatsapp,
    devise: (rawSettingValue(items.devise) || emptyForm.devise) as 'FCFA',
    delai_annulation_heures: rawSettingValue(items.delai_annulation_heures) || emptyForm.delai_annulation_heures,
    seuil_retard_minutes: rawSettingValue(items.seuil_retard_minutes) || emptyForm.seuil_retard_minutes,
    seuil_absence_minutes: rawSettingValue(items.seuil_absence_minutes) || emptyForm.seuil_absence_minutes,
    limite_reservations_par_jour: rawSettingValue(items.limite_reservations_par_jour) || emptyForm.limite_reservations_par_jour,
    limite_reservations_par_creneau: rawSettingValue(items.limite_reservations_par_creneau) || emptyForm.limite_reservations_par_creneau,
  }
}

function money(value: string, currency: string) {
  return `${Number(value || 0).toLocaleString('fr-FR')} ${currency}`
}

function minutesLabel(value: string) {
  const minutes = Number(value || 0)

  if (minutes < 60) {
    return `${minutes} min`
  }

  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`
}

function Field({
  label,
  children,
  hint,
}: {
  label: string
  children: React.ReactNode
  hint?: string
}) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-gray-500">
        {label}
      </span>
      {children}
      {hint && <span className="mt-1.5 block text-xs font-semibold text-gray-400">{hint}</span>}
    </label>
  )
}

function Panel({
  title,
  icon,
  children,
}: {
  title: string
  icon: React.ReactNode
  children: React.ReactNode
}) {
  return (
    <section className="rounded-xl border border-[#f1e7ee] bg-white p-4 shadow-[0_16px_34px_-30px_rgba(20,20,43,0.5)] sm:p-5">
      <div className="mb-5 flex items-center gap-3">
        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#fff2f7] text-[#e91e63]">
          {icon}
        </span>
        <h2 className="text-xl font-black text-[#111018]">{title}</h2>
      </div>
      {children}
    </section>
  )
}

function SettingsPage() {
  const [settings, setSettings] = useState<Record<string, SystemSetting>>({})
  const [form, setForm] = useState<ReservationSettingsForm>(emptyForm)
  const [initialForm, setInitialForm] = useState<ReservationSettingsForm>(emptyForm)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const hasChanges = useMemo(
    () => settingKeys.some((key) => form[key] !== initialForm[key]),
    [form, initialForm],
  )

  const summary = useMemo(
    () => [
      {
        label: 'Acompte fixe',
        value: money(form.montant_acompte_defaut, form.devise),
      },
      {
        label: 'Acompte proportionnel',
        value: `${Number(form.pourcentage_acompte || 0).toLocaleString('fr-FR')}%`,
      },
      {
        label: 'Horaires',
        value: `${form.heure_ouverture} - ${form.heure_fermeture}`,
      },
      {
        label: 'Annulation',
        value: `${Number(form.delai_annulation_heures || 0).toLocaleString('fr-FR')} h`,
      },
      {
        label: 'Journee',
        value: `${Number(form.limite_reservations_par_jour || 0).toLocaleString('fr-FR')} reservations`,
      },
      {
        label: 'Meme heure',
        value: `${Number(form.limite_reservations_par_creneau || 0).toLocaleString('fr-FR')} reservations`,
      },
      {
        label: 'Retard',
        value: minutesLabel(form.seuil_retard_minutes),
      },
      {
        label: 'Absence',
        value: minutesLabel(form.seuil_absence_minutes),
      },
    ],
    [form],
  )

  const applySettings = useCallback((items: SystemSetting[]) => {
    const mapped = items.reduce<Record<string, SystemSetting>>((nextItems, setting) => {
      nextItems[setting.cle] = setting
      return nextItems
    }, {})
    const nextForm = buildSettingsForm(mapped)

    setSettings(mapped)
    setForm(nextForm)
    setInitialForm(nextForm)
  }, [])

  const loadSettings = async () => {
    setLoading(true)
    setError(null)
    setSuccess(null)
    try {
      const response = await getSystemSettings({ page: 1, per_page: 100 })
      applySettings(response.data)
    } catch {
      setError('Impossible de charger les parametres.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    getSystemSettings({ page: 1, per_page: 100 })
      .then((response) => applySettings(response.data))
      .catch(() => setError('Impossible de charger les parametres.'))
      .finally(() => setLoading(false))
  }, [applySettings])

  const setField = (key: SettingKey, value: string) => {
    setForm((current) => ({
      ...current,
      [key]: value,
    }))
  }

  const validateForm = () => {
    const amount = Number(form.montant_acompte_defaut)
    const percent = Number(form.pourcentage_acompte)
    const cancelHours = Number(form.delai_annulation_heures)
    const lateMinutes = Number(form.seuil_retard_minutes)
    const absenceMinutes = Number(form.seuil_absence_minutes)
    const dailyLimit = Number(form.limite_reservations_par_jour)
    const slotLimit = Number(form.limite_reservations_par_creneau)

    if ([amount, percent, cancelHours, lateMinutes, absenceMinutes, dailyLimit, slotLimit].some((value) => Number.isNaN(value))) {
      return 'Les valeurs numeriques doivent etre valides.'
    }

    if (amount < 0 || percent < 0 || percent > 100 || cancelHours < 0 || lateMinutes < 0 || absenceMinutes < 0) {
      return 'Les valeurs doivent rester dans des limites positives et coherentes.'
    }

    if (!Number.isInteger(dailyLimit) || dailyLimit < 1 || dailyLimit > 200) {
      return 'La limite de reservations par jour doit etre entre 1 et 200.'
    }

    if (!Number.isInteger(slotLimit) || slotLimit < 1 || slotLimit > 50) {
      return 'La limite de reservations par heure doit etre entre 1 et 50.'
    }

    if (form.heure_ouverture >= form.heure_fermeture) {
      return 'L heure de fermeture doit etre apres l heure d ouverture.'
    }

    if (!/^\+?[0-9\s().-]{8,30}$/.test(form.telephone_whatsapp.trim())) {
      return 'Le telephone WhatsApp doit contenir entre 8 et 30 caracteres numeriques.'
    }

    if (absenceMinutes < lateMinutes) {
      return 'Le seuil absence doit etre superieur ou egal au seuil retard.'
    }

    return null
  }

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)
    setSuccess(null)

    const validationError = validateForm()
    if (validationError) {
      setError(validationError)
      return
    }

    setSaving(true)
    try {
      const changedKeys = settingKeys.filter((key) => form[key] !== initialForm[key])
      const updated = await Promise.all(
        changedKeys.map((key) => {
          const setting = settings[key]

          if (!setting) {
            throw new Error(`Missing setting: ${key}`)
          }

          return updateSystemSetting(setting, form[key])
        }),
      )

      const nextSettings = {
        ...settings,
        ...updated.reduce<Record<string, SystemSetting>>((items, setting) => {
          items[setting.cle] = setting
          return items
        }, {}),
      }
      const nextForm = buildSettingsForm(nextSettings)

      setSettings(nextSettings)
      setForm(nextForm)
      setInitialForm(nextForm)
      setSuccess('Parametres sauvegardes.')
    } catch {
      setError('Sauvegarde impossible. Verifiez les valeurs.')
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <AdminLayout>
        <div className="rounded-xl bg-white p-8 text-sm font-bold text-gray-600">
          Chargement des parametres...
        </div>
      </AdminLayout>
    )
  }

  return (
    <AdminLayout>
      <form onSubmit={submit}>
        <div className="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#e91e63]">
              Configuration salon
            </p>
            <h1 className="mt-2 text-2xl font-black text-[#111018] sm:text-3xl">Parametres</h1>
            <p className="mt-2 max-w-3xl text-sm font-medium text-gray-500">
              Regles appliquees aux prochaines reservations, aux acomptes et aux relances WhatsApp.
            </p>
          </div>
          <div className="flex flex-col gap-2 sm:flex-row">
            <button type="button" onClick={() => void loadSettings()} className={`${secondaryButtonClass} inline-flex items-center justify-center gap-2`}>
              <RefreshCw className="h-4 w-4" />
              Actualiser
            </button>
            <button type="submit" disabled={saving || !hasChanges} className={`${primaryButtonClass} inline-flex items-center justify-center gap-2`}>
              {saving ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
              {saving ? 'Sauvegarde...' : 'Sauvegarder'}
            </button>
          </div>
        </div>

        {error && (
          <div className="mb-5 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
            {error}
          </div>
        )}
        {success && (
          <div className="mb-5 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">
            {success}
          </div>
        )}

        <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {summary.map((item) => (
            <div key={item.label} className="rounded-xl border border-[#f1e7ee] bg-white px-4 py-3 shadow-[0_14px_30px_-28px_rgba(20,20,43,0.5)]">
              <p className="text-xs font-black uppercase tracking-[0.08em] text-gray-400">{item.label}</p>
              <p className="mt-1 text-lg font-black text-[#111018]">{item.value}</p>
            </div>
          ))}
        </section>

        <div className="grid gap-5 xl:grid-cols-[1fr_1fr]">
          <Panel title="Acomptes et devise" icon={<WalletCards className="h-5 w-5" />}>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Montant acompte par defaut" hint={money(form.montant_acompte_defaut, form.devise)}>
                <input
                  className={inputClass}
                  type="number"
                  min="0"
                  step="100"
                  value={form.montant_acompte_defaut}
                  onChange={(event) => setField('montant_acompte_defaut', event.target.value)}
                />
              </Field>
              <Field label="Pourcentage acompte">
                <div className="relative">
                  <input
                    className={`${inputClass} pr-10`}
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    value={form.pourcentage_acompte}
                    onChange={(event) => setField('pourcentage_acompte', event.target.value)}
                  />
                  <BadgePercent className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                </div>
              </Field>
              <Field label="Devise">
                <select
                  className={inputClass}
                  value={form.devise}
                  onChange={(event) => setField('devise', event.target.value)}
                >
                  <option value="FCFA">FCFA</option>
                </select>
              </Field>
            </div>
          </Panel>

          <Panel title="Horaires et WhatsApp" icon={<MessageCircle className="h-5 w-5" />}>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Heure ouverture">
                <div className="relative">
                  <input
                    className={`${inputClass} pr-10`}
                    type="time"
                    value={form.heure_ouverture}
                    onChange={(event) => setField('heure_ouverture', event.target.value)}
                  />
                  <Clock3 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                </div>
              </Field>
              <Field label="Heure fermeture">
                <div className="relative">
                  <input
                    className={`${inputClass} pr-10`}
                    type="time"
                    value={form.heure_fermeture}
                    onChange={(event) => setField('heure_fermeture', event.target.value)}
                  />
                  <Clock3 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                </div>
              </Field>
              <Field label="Telephone WhatsApp">
                <input
                  className={inputClass}
                  value={form.telephone_whatsapp}
                  onChange={(event) => setField('telephone_whatsapp', event.target.value)}
                  placeholder="+221 77 000 00 00"
                />
              </Field>
            </div>
          </Panel>

          <Panel title="Annulation et presence" icon={<ShieldAlert className="h-5 w-5" />}>
            <div className="grid gap-4 sm:grid-cols-3">
              <Field label="Delai annulation" hint="Heures avant rendez-vous">
                <input
                  className={inputClass}
                  type="number"
                  min="0"
                  max="168"
                  value={form.delai_annulation_heures}
                  onChange={(event) => setField('delai_annulation_heures', event.target.value)}
                />
              </Field>
              <Field label="Seuil retard" hint={minutesLabel(form.seuil_retard_minutes)}>
                <input
                  className={inputClass}
                  type="number"
                  min="0"
                  max="240"
                  value={form.seuil_retard_minutes}
                  onChange={(event) => setField('seuil_retard_minutes', event.target.value)}
                />
              </Field>
              <Field label="Seuil absence" hint={minutesLabel(form.seuil_absence_minutes)}>
                <input
                  className={inputClass}
                  type="number"
                  min="0"
                  max="240"
                  value={form.seuil_absence_minutes}
                  onChange={(event) => setField('seuil_absence_minutes', event.target.value)}
                />
              </Field>
            </div>
          </Panel>

          <Panel title="Capacite reservations" icon={<CalendarDays className="h-5 w-5" />}>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Reservations par jour" hint="Exemple : 10 ou 15 reservations maximum sur une journee">
                <input
                  className={inputClass}
                  type="number"
                  min="1"
                  max="200"
                  step="1"
                  value={form.limite_reservations_par_jour}
                  onChange={(event) => setField('limite_reservations_par_jour', event.target.value)}
                />
              </Field>
              <Field label="Reservations meme heure" hint="Exemple : 2 ou 3 clientes possibles a la meme heure">
                <input
                  className={inputClass}
                  type="number"
                  min="1"
                  max="50"
                  step="1"
                  value={form.limite_reservations_par_creneau}
                  onChange={(event) => setField('limite_reservations_par_creneau', event.target.value)}
                />
              </Field>
            </div>
            <div className="mt-4 rounded-xl bg-[#fff8fb] px-4 py-3 text-sm font-bold text-[#9b174f]">
              Ces limites servent a fermer automatiquement les heures pleines cote client et a bloquer les surreservations cote admin.
            </div>
          </Panel>
        </div>
      </form>
    </AdminLayout>
  )
}

export default SettingsPage
