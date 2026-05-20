import type { ReactNode } from 'react'
import { useState } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import { CalendarDays, CreditCard, LogOut, Menu, Users, X } from 'lucide-react'
import { clearAuth, getUser } from '../lib/authStorage'
import { logout as apiLogout } from '../services/authService'

type GeranteLayoutProps = {
  children: ReactNode
}

function Brand() {
  return (
    <div className="flex shrink-0 items-center gap-3 px-2">
      <div className="flex h-[50px] w-[50px] items-center justify-center rounded-full bg-[#ff2f85]/15 text-lg font-black text-[#ff4f9a]">
        BT
      </div>
      <div>
        <p className="font-display text-[24px] leading-5 text-[#ff5ca5]">Bichette</p>
        <p className="font-display text-[24px] leading-5 text-white">Thomas</p>
        <p className="text-[11px] text-white/65">Espace gerante</p>
      </div>
    </div>
  )
}

function GeranteLayout({ children }: GeranteLayoutProps) {
  const navigate = useNavigate()
  const user = getUser()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  const closeMobileMenu = () => setMobileMenuOpen(false)

  const handleLogout = async () => {
    try {
      await apiLogout('current')
    } catch {
      // Ignore : on nettoie l etat local et on redirige quand meme.
    }
    clearAuth()
    closeMobileMenu()
    navigate('/login', { replace: true })
  }

  const renderNavigation = () => (
    <>
      <div className="mb-4">
        <Brand />
      </div>

      <nav className="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto pr-1">
        <NavLink
          to="/manager/reservations"
          onClick={closeMobileMenu}
          className={({ isActive }) =>
            [
              'flex items-center gap-3 rounded-xl px-4 py-2 text-[13px] font-semibold transition',
              isActive
                ? 'bg-[#e91e63] text-white shadow-[0_14px_30px_-18px_rgba(233,30,99,0.9)]'
                : 'text-white/85 hover:bg-white/8 hover:text-white',
            ].join(' ')
          }
        >
          <CalendarDays className="h-4 w-4 shrink-0" />
          <span className="min-w-0 flex-1 truncate">Reservations</span>
        </NavLink>
        <NavLink
          to="/manager/clients"
          onClick={closeMobileMenu}
          className={({ isActive }) =>
            [
              'flex items-center gap-3 rounded-xl px-4 py-2 text-[13px] font-semibold transition',
              isActive
                ? 'bg-[#e91e63] text-white shadow-[0_14px_30px_-18px_rgba(233,30,99,0.9)]'
                : 'text-white/85 hover:bg-white/8 hover:text-white',
            ].join(' ')
          }
        >
          <Users className="h-4 w-4 shrink-0" />
          <span className="min-w-0 flex-1 truncate">Clientes</span>
        </NavLink>
        <NavLink
          to="/manager/paiements"
          onClick={closeMobileMenu}
          className={({ isActive }) =>
            [
              'flex items-center gap-3 rounded-xl px-4 py-2 text-[13px] font-semibold transition',
              isActive
                ? 'bg-[#e91e63] text-white shadow-[0_14px_30px_-18px_rgba(233,30,99,0.9)]'
                : 'text-white/85 hover:bg-white/8 hover:text-white',
            ].join(' ')
          }
        >
          <CreditCard className="h-4 w-4 shrink-0" />
          <span className="min-w-0 flex-1 truncate">Paiements</span>
        </NavLink>
      </nav>

      <div className="mt-2 shrink-0 rounded-xl bg-[#c41468] px-3 py-2.5">
        <p className="truncate text-[13px] font-bold leading-tight">{user?.name ?? 'Gerante'}</p>
        <p className="truncate text-[11px] leading-tight text-white/75">Gerante</p>
        <button
          type="button"
          onClick={() => void handleLogout()}
          className="mt-2 inline-flex items-center gap-2 rounded-lg bg-white/15 px-3 py-1.5 text-[11px] font-semibold text-white transition hover:bg-white/25"
        >
          <LogOut className="h-3.5 w-3.5" />
          Se deconnecter
        </button>
      </div>
    </>
  )

  return (
    <div className="min-h-screen bg-[#faf9fa] text-[#17141b]">
      <header className="sticky top-0 z-30 flex items-center justify-between border-b border-gray-100 bg-white/95 px-4 py-3 shadow-sm backdrop-blur lg:hidden">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[#ff2f85]/10 text-sm font-black text-[#e91e63]">
            BT
          </div>
          <div>
            <p className="font-display text-lg leading-5 text-[#e91e63]">Bichette Thomas</p>
            <p className="text-[11px] font-semibold text-gray-500">Espace gerante</p>
          </div>
        </div>
        <button
          type="button"
          onClick={() => setMobileMenuOpen(true)}
          className="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-100 bg-white text-gray-900 shadow-sm"
          aria-label="Ouvrir le menu"
        >
          <Menu className="h-5 w-5" />
        </button>
      </header>

      <aside className="fixed inset-y-0 left-0 z-30 hidden w-[252px] flex-col overflow-hidden bg-[#070711] px-3 py-3 text-white lg:flex">
        {renderNavigation()}
      </aside>

      {mobileMenuOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <button
            type="button"
            aria-label="Fermer le menu"
            className="absolute inset-0 bg-black/45"
            onClick={closeMobileMenu}
          />
          <aside className="absolute inset-y-0 left-0 flex w-[min(82vw,310px)] flex-col overflow-hidden bg-[#070711] px-3 py-3 text-white shadow-2xl">
            <button
              type="button"
              onClick={closeMobileMenu}
              className="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-white"
              aria-label="Fermer le menu"
            >
              <X className="h-5 w-5" />
            </button>
            {renderNavigation()}
          </aside>
        </div>
      )}

      <main className="min-h-screen lg:pl-[252px]">
        <div className="mx-auto w-full max-w-[1260px] px-3 py-4 sm:px-5 lg:px-7">
          {children}
        </div>
      </main>
    </div>
  )
}

export default GeranteLayout
