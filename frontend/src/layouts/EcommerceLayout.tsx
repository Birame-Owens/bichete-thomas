import { ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { LogOut, ShoppingCart, Package, FolderOpen, FileText } from 'lucide-react'
import { logout } from '../services/authService'
import { getUser, clearAuth } from '../lib/authStorage'

interface EcommerceLayoutProps {
  children: ReactNode
}

export default function EcommerceLayout({ children }: EcommerceLayoutProps) {
  const navigate = useNavigate()
  const user = getUser()

  const handleLogout = async () => {
    try {
      await logout('current')
    } catch {
      // Ignore errors
    }
    clearAuth()
    navigate('/console-thomas')
  }

  return (
    <div className="min-h-screen bg-gray-50 flex">
      {/* Sidebar */}
      <div className="w-64 bg-white shadow-md p-6">
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-pink-600">Ecommerce</h1>
          <p className="text-sm text-gray-600">Admin Panel</p>
        </div>

        <nav className="space-y-2">
          <NavLink icon={ShoppingCart} label="Vue générale" to="/console-thomas/ecommerce" />
          <NavLink icon={Package} label="Produits" to="/console-thomas/ecommerce/produits" />
          <NavLink icon={FolderOpen} label="Catégories" to="/console-thomas/ecommerce/categories" />
          <NavLink icon={FileText} label="Commandes" to="/console-thomas/ecommerce/commandes" />
        </nav>

        <div className="mt-12 pt-6 border-t">
          <p className="text-xs text-gray-600 mb-4">{user?.email}</p>
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded"
          >
            <LogOut size={16} />
            Déconnexion
          </button>
        </div>
      </div>

      {/* Main content */}
      <div className="flex-1">
        <div className="p-8">
          {children}
        </div>
      </div>
    </div>
  )
}

function NavLink({ icon: Icon, label, to }: { icon: any; label: string; to: string }) {
  const navigate = useNavigate()
  const isActive = location.pathname === to

  return (
    <button
      onClick={() => navigate(to)}
      className={`w-full flex items-center gap-3 px-4 py-2 rounded text-sm font-medium transition ${
        isActive
          ? 'bg-pink-100 text-pink-700'
          : 'text-gray-700 hover:bg-gray-100'
      }`}
    >
      <Icon size={16} />
      {label}
    </button>
  )
}
