import { useEffect, useState } from 'react'
import { NavLink } from 'react-router-dom'
import { BadgeCheck, Scissors, ShieldCheck, Users } from 'lucide-react'
import PersonnelLayout from './components/PersonnelLayout'
import { EmptyState, ErrorState, Panel, primaryButtonClass } from './components/PersonnelUi'
import { getCoiffeuses, getGerantes } from './personnel.api'

type PersonnelStats = {
  gerantes: number
  gerantesActives: number
  coiffeuses: number
  coiffeusesActives: number
}

const modules = [
  {
    label: 'Gerantes',
    path: '/admin/personnel/gerantes',
    description: 'Comptes responsables pouvant acceder aux espaces internes.',
    icon: ShieldCheck,
    statKey: 'gerantes',
    activeKey: 'gerantesActives',
  },
  {
    label: 'Coiffeuses',
    path: '/admin/personnel/coiffeuses',
    description: 'Equipe operationnelle, commissions et disponibilite.',
    icon: Scissors,
    statKey: 'coiffeuses',
    activeKey: 'coiffeusesActives',
  },
] as const

function PersonnelOverviewPage() {
  const [stats, setStats] = useState<PersonnelStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    async function load() {
      setLoading(true)
      setError(null)
      try {
        const [gerantes, gerantesActives, coiffeuses, coiffeusesActives] = await Promise.all([
          getGerantes(),
          getGerantes({ actif: true }),
          getCoiffeuses(),
          getCoiffeuses({ actif: true }),
        ])

        setStats({
          gerantes: gerantes.total,
          gerantesActives: gerantesActives.total,
          coiffeuses: coiffeuses.total,
          coiffeusesActives: coiffeusesActives.total,
        })
      } catch {
        setError('Impossible de charger le resume du personnel.')
      } finally {
        setLoading(false)
      }
    }

    void load()
  }, [])

  return (
    <PersonnelLayout
      title="Personnel"
      subtitle="Gerez les comptes gerantes et l equipe de coiffeuses depuis un espace clair."
    >
      {error && <ErrorState label={error} />}
      {loading || !stats ? (
        <EmptyState label="Chargement du personnel..." />
      ) : (
        <div className="grid gap-5 xl:grid-cols-[1fr_360px]">
          <div className="grid gap-4 md:grid-cols-2">
            {modules.map((module) => {
              const Icon = module.icon
              const count = stats[module.statKey]
              const active = stats[module.activeKey]

              return (
                <NavLink
                  key={module.path}
                  to={module.path}
                  className="rounded-xl border border-[#f1e7ee] bg-white p-5 shadow-[0_16px_34px_-30px_rgba(20,20,43,0.5)] transition hover:-translate-y-0.5 hover:border-[#f7b1cf]"
                >
                  <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                      <div className="flex items-center gap-3">
                        <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#fff2f7] text-[#e91e63]">
                          <Icon className="h-5 w-5" />
                        </span>
                        <h2 className="text-xl font-black text-gray-950">{module.label}</h2>
                      </div>
                      <p className="mt-3 text-sm font-medium text-gray-500">{module.description}</p>
                    </div>
                    <span className="rounded-2xl bg-[#fff2f7] px-4 py-3 text-2xl font-black text-[#e91e63]">
                      {count.toLocaleString('fr-FR')}
                    </span>
                  </div>
                  <div className="mt-8 flex items-center justify-between gap-3 text-sm font-black">
                    <span className="inline-flex items-center gap-2 text-emerald-700">
                      <BadgeCheck className="h-4 w-4" />
                      {active.toLocaleString('fr-FR')} actif(s)
                    </span>
                    <span className="text-[#c41468]">Ouvrir</span>
                  </div>
                </NavLink>
              )
            })}
          </div>

          <Panel title="Pilotage rapide" subtitle="Les controles essentiels restent accessibles sur mobile comme desktop.">
            <div className="space-y-3 text-sm font-bold text-gray-600">
              <div className="rounded-lg bg-[#fff7fb] px-3 py-3">
                Creer une gerante avec son email et un mot de passe temporaire.
              </div>
              <div className="rounded-lg bg-[#fff7fb] px-3 py-3">
                Desactiver un compte bloque immediatement les prochains acces API.
              </div>
              <div className="rounded-lg bg-[#fff7fb] px-3 py-3">
                Suivre la commission de chaque coiffeuse depuis sa fiche.
              </div>
            </div>
            <NavLink to="/admin/personnel/coiffeuses" className={`mt-5 inline-flex items-center gap-2 ${primaryButtonClass}`}>
              <Users className="h-4 w-4" />
              Voir l equipe
            </NavLink>
          </Panel>
        </div>
      )}
    </PersonnelLayout>
  )
}

export default PersonnelOverviewPage
