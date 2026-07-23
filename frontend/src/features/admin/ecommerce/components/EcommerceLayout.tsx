import type { ReactNode } from 'react'
import { NavLink } from 'react-router-dom'
import AdminLayout from '../../../../layouts/AdminLayout'

type EcommerceLayoutProps = {
  children: ReactNode
}

const tabs = [
  { label: 'Vue générale', path: '/console-thomas/ecommerce' },
  { label: 'Produits', path: '/console-thomas/ecommerce/produits' },
  { label: 'Catégories', path: '/console-thomas/ecommerce/categories' },
  { label: 'Commandes', path: '/console-thomas/ecommerce/commandes' },
]

export function EcommerceLayout({ children }: EcommerceLayoutProps) {
  return (
    <AdminLayout>
      <div className="space-y-6">
        {/* Tabs */}
        <div className="border-b border-gray-200">
          <nav className="flex gap-8" aria-label="Ecommerce sections">
            {tabs.map(tab => (
              <NavLink
                key={tab.path}
                to={tab.path}
                className={({ isActive }) =>
                  `px-1 py-4 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
                    isActive
                      ? 'border-[#ff5ca5] text-[#ff5ca5]'
                      : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300'
                  }`
                }
              >
                {tab.label}
              </NavLink>
            ))}
          </nav>
        </div>

        {/* Content */}
        <div>{children}</div>
      </div>
    </AdminLayout>
  )
}
