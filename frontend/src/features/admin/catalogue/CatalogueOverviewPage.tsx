import { useEffect, useState } from 'react'
import { NavLink } from 'react-router-dom'
import CatalogueLayout from './components/CatalogueLayout'
import { EmptyState, ErrorState, Panel } from './components/CatalogueUi'
import { primaryButtonClass } from './components/catalogueUiTokens'
import {
  getCategoriesCoiffures,
  getCoiffures,
  getOptionsCoiffures,
  getVariantesCoiffures,
} from './catalogue.api'

type CatalogueStats = {
  categories: number
  coiffures: number
  variantes: number
  options: number
}

const modules = [
  {
    label: 'Categories',
    path: '/admin/catalogue/categories-coiffures',
    description: 'Familles de prestations pour structurer le catalogue.',
  },
  {
    label: 'Coiffures',
    path: '/admin/catalogue/coiffures',
    description: 'Prestations principales visibles et reservables.',
  },
  {
    label: 'Variantes',
    path: '/admin/catalogue/variantes',
    description: 'Prix, durees et formats rattaches aux coiffures.',
  },
  {
    label: 'Options',
    path: '/admin/catalogue/options',
    description: 'Supplements et personnalisations du service.',
  },
]

function CatalogueOverviewPage() {
  const [stats, setStats] = useState<CatalogueStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    async function load() {
      setLoading(true)
      setError(null)
      try {
        const [categories, coiffures, variantes, options] = await Promise.all([
          getCategoriesCoiffures(),
          getCoiffures(),
          getVariantesCoiffures(),
          getOptionsCoiffures(),
        ])

        setStats({
          categories: categories.total,
          coiffures: coiffures.total,
          variantes: variantes.total,
          options: options.total,
        })
      } catch {
        setError('Impossible de charger le resume du catalogue.')
      } finally {
        setLoading(false)
      }
    }

    void load()
  }, [])

  return (
    <CatalogueLayout
      title="Catalogue"
      subtitle="Pilotez les categories, coiffures, variantes et options depuis un seul espace admin."
    >
      {error && <ErrorState label={error} />}
      {loading || !stats ? (
        <EmptyState label="Chargement du catalogue..." />
      ) : (
        <div className="grid gap-5 xl:grid-cols-[1fr_360px]">
          <div className="grid gap-4 md:grid-cols-2">
            {modules.map((module) => {
              const count = stats[module.label.toLowerCase() as keyof CatalogueStats]

              return (
                <NavLink
                  key={module.path}
                  to={module.path}
                  className="rounded-xl border border-[#f1e7ee] bg-white p-5 shadow-[0_16px_34px_-30px_rgba(20,20,43,0.5)] transition hover:-translate-y-0.5 hover:border-[#f7b1cf]"
                >
                  <div className="flex items-start justify-between gap-4">
                    <div>
                      <h2 className="text-xl font-black text-gray-950">{module.label}</h2>
                      <p className="mt-2 text-sm font-medium text-gray-500">{module.description}</p>
                    </div>
                    <span className="rounded-2xl bg-[#fff2f7] px-4 py-3 text-2xl font-black text-[#e91e63]">
                      {count.toLocaleString('fr-FR')}
                    </span>
                  </div>
                  <p className="mt-8 text-sm font-black text-[#c41468]">Ouvrir le module</p>
                </NavLink>
              )
            })}
          </div>

          <Panel title="Ordre conseille" subtitle="Pour remplir le catalogue sans blocage.">
            <ol className="space-y-3 text-sm font-bold text-gray-600">
              <li className="rounded-lg bg-[#fff7fb] px-3 py-3">1. Creer les categories.</li>
              <li className="rounded-lg bg-[#fff7fb] px-3 py-3">2. Creer les options communes.</li>
              <li className="rounded-lg bg-[#fff7fb] px-3 py-3">3. Ajouter les coiffures.</li>
              <li className="rounded-lg bg-[#fff7fb] px-3 py-3">4. Ajouter les variantes et prix.</li>
            </ol>
            <NavLink to="/admin/catalogue/coiffures" className={`mt-5 inline-flex ${primaryButtonClass}`}>
              Ajouter une coiffure
            </NavLink>
          </Panel>
        </div>
      )}
    </CatalogueLayout>
  )
}

export default CatalogueOverviewPage
