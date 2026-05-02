import type { ReactNode } from 'react'
import { NavLink } from 'react-router-dom'
import AdminLayout from '../../../../layouts/AdminLayout'

const tabs = [
  { label: 'Vue catalogue', path: '/admin/catalogue' },
  { label: 'Categories', path: '/admin/catalogue/categories-coiffures' },
  { label: 'Coiffures', path: '/admin/catalogue/coiffures' },
  { label: 'Options', path: '/admin/catalogue/options' },
]

type CatalogueLayoutProps = {
  title: string
  subtitle: string
  children: ReactNode
  action?: ReactNode
}

function CatalogueLayout({ title, subtitle, children, action }: CatalogueLayoutProps) {
  return (
    <AdminLayout>
      <div className="mb-5 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#e91e63]">
            Module catalogue
          </p>
          <h1 className="mt-2 text-3xl font-black text-[#111018]">{title}</h1>
          <p className="mt-2 max-w-3xl text-sm font-medium text-gray-500">{subtitle}</p>
        </div>
        {action}
      </div>

      <div className="mb-5 flex gap-2 overflow-x-auto rounded-xl border border-[#f1e7ee] bg-white p-1 shadow-[0_14px_32px_-28px_rgba(20,20,43,0.55)]">
        {tabs.map((tab) => (
          <NavLink
            key={tab.path}
            to={tab.path}
            end={tab.path === '/admin/catalogue'}
            className={({ isActive }) =>
              [
                'whitespace-nowrap rounded-lg px-4 py-2 text-sm font-bold transition',
                isActive ? 'bg-[#e91e63] text-white' : 'text-gray-500 hover:bg-[#fff2f7] hover:text-[#c41468]',
              ].join(' ')
            }
          >
            {tab.label}
          </NavLink>
        ))}
      </div>

      {children}
    </AdminLayout>
  )
}

export default CatalogueLayout
