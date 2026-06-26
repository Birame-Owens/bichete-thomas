import type { ReactNode } from 'react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import {
  BarChart3,
  Bell,
  BookOpen,
  CalendarDays,
  Camera,
  ChevronDown,
  ChevronUp,
  CircleDollarSign,
  ClipboardList,
  Gauge,
  Gift,
  LogOut,
  Menu,
  MessageCircle,
  Percent,
  Receipt,
  Settings,
  ShoppingBag,
  ShoppingCart,
  Star,
  Users,
  Wallet,
  X,
  type LucideIcon,
} from 'lucide-react'
import { clearAuth, getUser } from '../lib/authStorage'
import { logout as apiLogout } from '../services/authService'
import { getNonLusCount, getAdminSignalements, marquerLu, marquerTraite } from '../features/admin/signalements/signalements.api'
import type { Signalement } from '../features/admin/signalements/signalements.types'

type AdminLayoutProps = {
  children: ReactNode
}

type Section = {
  label: string
  path: string
  icon: LucideIcon
  comingSoon?: boolean
  children?: Array<{
    label: string
    path: string
  }>
}

const sections: Section[] = [
  { label: 'Tableau de bord', path: '/console-thomas/dashboard', icon: Gauge },
  { label: 'Reservations', path: '/console-thomas/reservations', icon: CalendarDays },
  { label: 'Clients', path: '/console-thomas/clients', icon: Users },
  {
    label: 'Catalogue',
    path: '/console-thomas/catalogue',
    icon: BookOpen,
    children: [
      { label: 'Categories coiffures', path: '/console-thomas/catalogue/categories-coiffures' },
      { label: 'Coiffures', path: '/console-thomas/catalogue/coiffures' },
      { label: 'Options', path: '/console-thomas/catalogue/options' },
      { label: 'Galerie accueil', path: '/console-thomas/catalogue/galerie' },
    ],
  },
  {
    label: 'Personnel',
    path: '/console-thomas/personnel',
    icon: ClipboardList,
    children: [
      { label: 'Gerantes', path: '/console-thomas/personnel/gerantes' },
      { label: 'Coiffeuses', path: '/console-thomas/personnel/coiffeuses' },
    ],
  },
  { label: 'Paiements & Recus', path: '/console-thomas/paiements', icon: Receipt },
  { label: 'Caisse', path: '/console-thomas/caisse', icon: Wallet, comingSoon: true },
  { label: 'Depenses', path: '/console-thomas/depenses', icon: CircleDollarSign },
  { label: 'Commissions', path: '/console-thomas/commissions', icon: Percent, comingSoon: true },
  { label: 'Promotions & Fidelite', path: '/console-thomas/promotions', icon: Gift },
  {
    label: 'Ecommerce',
    path: '/console-thomas/ecommerce',
    icon: ShoppingCart,
    children: [
      { label: 'Vue generale', path: '/console-thomas/ecommerce' },
      { label: 'Produits', path: '/console-thomas/ecommerce/produits' },
      { label: 'Categories', path: '/console-thomas/ecommerce/categories' },
      { label: 'Commandes', path: '/console-thomas/ecommerce/commandes' },
    ],
  },
  { label: 'Avis clientes', path: '/console-thomas/avis', icon: Star },
  { label: 'Messages WhatsApp', path: '/console-thomas/messages', icon: MessageCircle, comingSoon: true },
  { label: 'Photos prestations', path: '/console-thomas/photos', icon: Camera, comingSoon: true },
  { label: 'Rapports & Statistiques', path: '/console-thomas/rapports', icon: BarChart3 },
  { label: 'Signalements', path: '/console-thomas/signalements', icon: Bell },
  { label: 'Logs systeme', path: '/console-thomas/logs', icon: ShoppingBag },
  { label: 'Parametres', path: '/console-thomas/parametres', icon: Settings },
]

function Brand() {
  return (
    <div className="flex shrink-0 items-center gap-3 px-2">
      <div className="flex h-[50px] w-[50px] items-center justify-center rounded-full bg-[#ff2f85]/15 text-lg font-black text-[#ff4f9a]">
        BT
      </div>
      <div>
        <p className="font-display text-[24px] leading-5 text-[#ff5ca5]">Bichette</p>
        <p className="font-display text-[24px] leading-5 text-white">Thomas</p>
        <p className="text-[11px] text-white/65">Salon de Coiffure</p>
      </div>
    </div>
  )
}

function NotifBell() {
  const [count, setCount]         = useState(0)
  const [open, setOpen]           = useState(false)
  const [items, setItems]         = useState<Signalement[]>([])
  const [noteMap, setNoteMap]     = useState<Record<number, string>>({})
  const ref                       = useRef<HTMLDivElement>(null)

  const loadCount = useCallback(async () => {
    try { setCount(await getNonLusCount()) } catch { /* silencieux */ }
  }, [])

  const loadItems = useCallback(async () => {
    try { setItems(await getAdminSignalements({ traite: false })) } catch { /* silencieux */ }
  }, [])

  // Polling toutes les 30s
  useEffect(() => {
    void loadCount()
    const id = window.setInterval(() => void loadCount(), 30_000)
    return () => window.clearInterval(id)
  }, [loadCount])

  // Fermer si clic en dehors
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const toggle = () => {
    setOpen((v) => {
      if (!v) void loadItems()
      return !v
    })
  }

  const handleLu = async (s: Signalement) => {
    if (s.lu_par_admin) return
    const updated = await marquerLu(s.id)
    setItems((prev) => prev.map((x) => (x.id === s.id ? updated : x)))
    setCount((c) => Math.max(0, c - 1))
  }

  const handleTraite = async (s: Signalement) => {
    const updated = await marquerTraite(s.id, noteMap[s.id] || undefined)
    setItems((prev) => prev.filter((x) => x.id !== updated.id))
    if (!s.lu_par_admin) setCount((c) => Math.max(0, c - 1))
  }

  const typeLabel = (t: string) => ({ produit: 'Produit', materiel: 'Materiel', autre: 'Autre' }[t] ?? t)

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={toggle}
        className="relative flex h-9 w-9 items-center justify-center rounded-xl border border-gray-100 bg-white text-gray-700 shadow-sm hover:bg-gray-50"
        aria-label="Notifications"
      >
        <Bell className="h-4.5 w-4.5 h-[18px] w-[18px]" />
        {count > 0 && (
          <span className="absolute -right-1 -top-1 flex h-4.5 h-[18px] w-[18px] items-center justify-center rounded-full bg-[#e91e63] text-[10px] font-black text-white">
            {count > 9 ? '9+' : count}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-11 z-50 w-[340px] overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-2xl">
          <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <p className="text-sm font-black text-gray-950">Signalements non traites</p>
            <NavLink to="/console-thomas/signalements" onClick={() => setOpen(false)} className="text-xs font-bold text-[#e91e63] hover:underline">
              Tout voir
            </NavLink>
          </div>
          <div className="max-h-[400px] overflow-y-auto">
            {items.length === 0 ? (
              <div className="px-4 py-8 text-center text-sm font-medium text-gray-400">Aucun signalement en attente.</div>
            ) : (
              items.map((s) => (
                <div
                  key={s.id}
                  className={['border-b border-gray-50 p-4 hover:bg-gray-50/50 cursor-pointer', !s.lu_par_admin ? 'bg-[#fff8fb]' : ''].join(' ')}
                  onClick={() => void handleLu(s)}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <div className="flex items-center gap-1.5">
                        {!s.lu_par_admin && <span className="h-2 w-2 shrink-0 rounded-full bg-[#e91e63]" />}
                        {s.urgence === 'urgente' && <span className="text-xs">🔴</span>}
                        <p className="truncate text-sm font-black text-gray-900">{s.titre}</p>
                      </div>
                      <p className="mt-0.5 text-xs text-gray-400">{typeLabel(s.type)} · {s.gerante?.name}</p>
                      {s.description && <p className="mt-1 line-clamp-2 text-xs text-gray-500">{s.description}</p>}
                    </div>
                  </div>
                  <div className="mt-2 flex gap-2" onClick={(e) => e.stopPropagation()}>
                    <input
                      type="text"
                      placeholder="Note (optionnel)..."
                      value={noteMap[s.id] ?? ''}
                      onChange={(e) => setNoteMap((m) => ({ ...m, [s.id]: e.target.value }))}
                      className="min-w-0 flex-1 rounded-lg border border-gray-200 px-2 py-1 text-xs outline-none focus:border-[#e91e63]"
                    />
                    <button
                      type="button"
                      onClick={() => void handleTraite(s)}
                      className="shrink-0 rounded-lg bg-emerald-500 px-2.5 py-1 text-xs font-black text-white hover:bg-emerald-600"
                    >
                      Traite
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  )
}

function AdminLayout({ children }: AdminLayoutProps) {
  const navigate = useNavigate()
  const user = getUser()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    '/console-thomas/catalogue': true,
    '/console-thomas/personnel': true,
    '/console-thomas/ecommerce': true,
  })
  const [comingSoonToast, setComingSoonToast] = useState(false)

  const showComingSoon = () => {
    setComingSoonToast(true)
    window.setTimeout(() => setComingSoonToast(false), 3000)
  }

  const closeMobileMenu = () => setMobileMenuOpen(false)

  const handleLogout = async () => {
    try {
      await apiLogout('current')
    } catch {
      // Ignore : on nettoie quand meme l etat local et on redirige.
    }
    clearAuth()
    closeMobileMenu()
    navigate('/console-thomas', { replace: true })
  }

  const renderNavigation = () => (
    <>
      <div className="mb-4">
        <Brand />
      </div>

      <nav className="sidebar-scroll flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto pr-1">
        {sections.map((section) => {
          const isOpen = openSections[section.path] ?? false
          const Icon = section.icon

          return (
            <div key={section.path}>
              {section.comingSoon ? (
                <button
                  type="button"
                  onClick={showComingSoon}
                  className="flex w-full cursor-not-allowed items-center gap-3 rounded-xl px-4 py-2 text-left text-[13px] font-semibold text-white/40 transition"
                >
                  <Icon className="h-4 w-4 shrink-0" />
                  <span className="min-w-0 flex-1 truncate">{section.label}</span>
                  <span className="shrink-0 rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-white/40">
                    Bientôt
                  </span>
                </button>
              ) : section.children ? (
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
                  <Icon className="h-4 w-4 shrink-0" />
                  <span className="min-w-0 flex-1 truncate">{section.label}</span>
                  {isOpen ? (
                    <ChevronUp className="h-4 w-4 text-white/55" />
                  ) : (
                    <ChevronDown className="h-4 w-4 text-white/55" />
                  )}
                </button>
              ) : (
                <NavLink
                  to={section.path}
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
                  <Icon className="h-4 w-4 shrink-0" />
                  <span className="min-w-0 flex-1 truncate">{section.label}</span>
                </NavLink>
              )}

              {section.children && isOpen && (
                <div className="ml-10 mt-1 space-y-1 pb-1">
                  {section.children.map((child) => (
                    <NavLink
                      key={child.path}
                      to={child.path}
                      onClick={closeMobileMenu}
                      className={({ isActive }) =>
                        [
                          'flex items-center gap-3 rounded-lg py-1 pr-2 text-[12px] font-semibold transition',
                          isActive ? 'text-[#ff5ca5]' : 'text-white/75 hover:text-white',
                        ].join(' ')
                      }
                    >
                      <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-[#ff4f9a]" />
                      <span className="min-w-0 truncate">{child.label}</span>
                    </NavLink>
                  ))}
                </div>
              )}
            </div>
          )
        })}
      </nav>

      <div className="mt-2 shrink-0 rounded-xl bg-[#c41468] px-3 py-2.5">
        <p className="truncate text-[13px] font-bold leading-tight">{user?.name ?? 'Administratrice'}</p>
        <p className="truncate text-[11px] leading-tight text-white/75">{user?.role ?? 'admin'}</p>
        <button
          type="button"
          onClick={handleLogout}
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
      {comingSoonToast && (
        <div className="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-2xl bg-[#17141b] px-5 py-3 text-sm font-bold text-white shadow-2xl">
          Fonctionnalité pas encore disponible
        </div>
      )}
      <header className="sticky top-0 z-30 flex items-center justify-between border-b border-gray-100 bg-white/95 px-4 py-3 shadow-sm backdrop-blur lg:hidden">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[#ff2f85]/10 text-sm font-black text-[#e91e63]">
            BT
          </div>
          <div>
            <p className="font-display text-lg leading-5 text-[#e91e63]">Bichette Thomas</p>
            <p className="text-[11px] font-semibold text-gray-500">Administration</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <NotifBell />
          <button
            type="button"
            onClick={() => setMobileMenuOpen(true)}
            className="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-100 bg-white text-gray-900 shadow-sm"
            aria-label="Ouvrir le menu"
          >
            <Menu className="h-5 w-5" />
          </button>
        </div>
      </header>

      <aside className="fixed inset-y-0 left-0 z-30 hidden w-[252px] flex-col overflow-hidden bg-[#070711] px-3 py-3 text-white lg:flex">
        {renderNavigation()}
      </aside>

      {/* Cloche desktop : coin supérieur droit */}
      <div className="fixed right-4 top-4 z-20 hidden lg:block">
        <NotifBell />
      </div>

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

export default AdminLayout
