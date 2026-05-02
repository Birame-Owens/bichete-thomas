import type { ReactNode } from 'react'
import { useState } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import { clearAuth, getUser } from '../lib/authStorage'

type AdminLayoutProps = {
  children: ReactNode
}

const sections = [
  { label: 'Tableau de bord', path: '/admin/dashboard', icon: '⌂' },
  { label: 'Reservations', path: '/admin/reservations', icon: '▣' },
  { label: 'Clients', path: '/admin/clients', icon: '◎' },
  {
    label: 'Catalogue',
    path: '/admin/catalogue',
    icon: '≡',
    children: [
      { label: 'Categories coiffures', path: '/admin/catalogue/categories-coiffures' },
      { label: 'Coiffures', path: '/admin/catalogue/coiffures' },
      { label: 'Variantes', path: '/admin/catalogue/variantes' },
      { label: 'Options', path: '/admin/catalogue/options' },
    ],
  },
  {
    label: 'Personnel',
    path: '/admin/personnel',
    icon: '♢',
    children: [
      { label: 'Gerantes', path: '/admin/personnel/gerantes' },
      { label: 'Coiffeuses', path: '/admin/personnel/coiffeuses' },
    ],
  },
  { label: 'Paiements & Recus', path: '/admin/paiements', icon: '$' },
  { label: 'Caisse', path: '/admin/caisse', icon: '□' },
  { label: 'Depenses', path: '/admin/depenses', icon: '-' },
  { label: 'Commissions', path: '/admin/commissions', icon: '%' },
  { label: 'Promotions & Fidelite', path: '/admin/promotions', icon: '+' },
  { label: 'Messages WhatsApp', path: '/admin/messages', icon: '@' },
  { label: 'Photos prestations', path: '/admin/photos', icon: '#' },
  { label: 'Rapports & Statistiques', path: '/admin/rapports', icon: '^' },
  { label: 'Logs systeme', path: '/admin/logs', icon: '~' },
  { label: 'Parametres', path: '/admin/parametres', icon: '*' },
]

function AdminLayout({ children }: AdminLayoutProps) {
  const navigate = useNavigate()
  const user = getUser()
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    '/admin/catalogue': true,
    '/admin/personnel': true,
  })

  const handleLogout = () => {
    clearAuth()
    navigate('/login', { replace: true })
  }

  return (
    <div className="min-h-screen bg-[#faf9fa] text-[#17141b]">
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-[252px] flex-col overflow-hidden bg-[#070711] px-3 py-3 text-white lg:flex">
        <div className="mb-4 flex shrink-0 items-center gap-3 px-2">
          <div className="flex h-[50px] w-[50px] items-center justify-center rounded-full bg-[#ff2f85]/15 text-lg font-black text-[#ff4f9a]">
            BT
          </div>
          <div>
            <p className="font-display text-[24px] leading-5 text-[#ff5ca5]">
              Bichette
            </p>
            <p className="font-display text-[24px] leading-5 text-white">Thomas</p>
            <p className="text-[11px] text-white/65">Salon de Coiffure</p>
          </div>
        </div>

        <nav className="sidebar-scroll flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto pr-1">
          {sections.map((section) => {
            const isOpen = openSections[section.path] ?? false

            return (
              <div key={section.path}>
                {section.children ? (
                  <button
                    type="button"
                    onClick={() =>
                      setOpenSections((current) => ({
                        ...current,
                        [section.path]: !isOpen,
                      }))
                    }
                    className="flex w-full items-center gap-3 rounded-xl px-4 py-2 text-left text-[13px] font-semibold text-white/85 transition hover:bg-white/8 hover:text-white"
                  >
                    <span className="flex h-5 w-5 items-center justify-center text-sm">
                      {section.icon}
                    </span>
                    <span className="flex-1">{section.label}</span>
                    <span className="text-white/55">{isOpen ? '⌃' : '⌄'}</span>
                  </button>
                ) : (
                  <NavLink
                    to={section.path}
                    className={({ isActive }) =>
                      [
                        'flex items-center gap-3 rounded-xl px-4 py-2 text-[13px] font-semibold transition',
                        isActive
                          ? 'bg-[#e91e63] text-white shadow-[0_14px_30px_-18px_rgba(233,30,99,0.9)]'
                          : 'text-white/85 hover:bg-white/8 hover:text-white',
                      ].join(' ')
                    }
                  >
                    <span className="flex h-5 w-5 items-center justify-center text-sm">
                      {section.icon}
                    </span>
                    <span className="flex-1">{section.label}</span>
                  </NavLink>
                )}

                {section.children && isOpen && (
                  <div className="ml-10 mt-1 space-y-1 pb-1">
                    {section.children.map((child) => (
                      <NavLink
                        key={child.path}
                        to={child.path}
                        className={({ isActive }) =>
                          [
                            'flex items-center gap-3 rounded-lg py-0.5 text-[12px] font-semibold transition',
                            isActive ? 'text-[#ff5ca5]' : 'text-white/75 hover:text-white',
                          ].join(' ')
                        }
                      >
                        <span className="h-1.5 w-1.5 rounded-full bg-[#ff4f9a]" />
                        {child.label}
                      </NavLink>
                    ))}
                  </div>
                )}
              </div>
            )
          })}
        </nav>

        <div className="mt-2 shrink-0 rounded-xl bg-[#c41468] px-3 py-2.5">
          <p className="text-[13px] font-bold leading-tight">{user?.name ?? 'Administratrice'}</p>
          <p className="text-[11px] leading-tight text-white/75">{user?.role ?? 'admin'}</p>
          <button
            type="button"
            onClick={handleLogout}
            className="mt-2 rounded-lg bg-white/15 px-3 py-1.5 text-[11px] font-semibold text-white transition hover:bg-white/25"
          >
            Se deconnecter
          </button>
        </div>
      </aside>

      <main className="min-h-screen lg:pl-[252px]">
        <div className="mx-auto w-full max-w-[1260px] px-5 py-5 sm:px-7">
          {children}
        </div>
      </main>
    </div>
  )
}

export default AdminLayout
