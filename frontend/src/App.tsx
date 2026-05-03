import { Navigate, Route, Routes } from 'react-router-dom'
import type { ReactNode } from 'react'
import LoginPage from './pages/LoginPage'
import AdminDashboardPage from './features/admin/dashboard/AdminDashboardPage'
import CatalogueOverviewPage from './features/admin/catalogue/CatalogueOverviewPage'
import CategoriesCoiffuresPage from './features/admin/catalogue/CategoriesCoiffuresPage'
import CoiffuresPage from './features/admin/catalogue/CoiffuresPage'
import OptionsCoiffuresPage from './features/admin/catalogue/OptionsCoiffuresPage'
import VariantesCoiffuresPage from './features/admin/catalogue/VariantesCoiffuresPage'
import PersonnelOverviewPage from './features/admin/personnel/PersonnelOverviewPage'
import GerantesPage from './features/admin/personnel/GerantesPage'
import CoiffeusesPage from './features/admin/personnel/CoiffeusesPage'
import SettingsPage from './features/admin/settings/SettingsPage'
import PromotionsPage from './features/admin/promotions/PromotionsPage'
import RequireAuth from './features/auth/RequireAuth'

function ManagerDashboard() {
  return (
    <div className="min-h-screen bg-[#faf9fa] px-6 py-12">
      <div className="mx-auto w-full max-w-3xl rounded-xl border border-gray-100 bg-white p-8 shadow-sm">
        <h1 className="text-3xl font-black text-gray-950">Espace gerante</h1>
        <p className="mt-3 text-sm font-semibold text-gray-500">
          Votre espace de pilotage sera branche sur les prochains modules operationnels.
        </p>
      </div>
    </div>
  )
}

function NotFound() {
  return (
    <div className="min-h-screen bg-white px-6 py-12">
      <div className="mx-auto w-full max-w-3xl rounded-xl border border-gray-100 bg-white p-10 shadow-sm">
        <h1 className="font-display text-3xl text-gray-900">Page introuvable</h1>
        <p className="mt-3 text-gray-600">
          La page demandee n existe pas.
        </p>
      </div>
    </div>
  )
}

function AdminRoute({ children }: { children: ReactNode }) {
  return <RequireAuth role="admin">{children}</RequireAuth>
}

function App() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/admin" element={<Navigate to="/admin/dashboard" replace />} />
      <Route
        path="/admin/dashboard"
        element={
          <AdminRoute>
            <AdminDashboardPage />
          </AdminRoute>
        }
      />
      <Route path="/admin/catalogue" element={<AdminRoute><CatalogueOverviewPage /></AdminRoute>} />
      <Route path="/admin/catalogue/categories-coiffures" element={<AdminRoute><CategoriesCoiffuresPage /></AdminRoute>} />
      <Route path="/admin/catalogue/coiffures" element={<AdminRoute><CoiffuresPage /></AdminRoute>} />
      <Route path="/admin/catalogue/variantes" element={<AdminRoute><VariantesCoiffuresPage /></AdminRoute>} />
      <Route path="/admin/catalogue/options" element={<AdminRoute><OptionsCoiffuresPage /></AdminRoute>} />
      <Route path="/admin/personnel" element={<AdminRoute><PersonnelOverviewPage /></AdminRoute>} />
      <Route path="/admin/personnel/gerantes" element={<AdminRoute><GerantesPage /></AdminRoute>} />
      <Route path="/admin/personnel/coiffeuses" element={<AdminRoute><CoiffeusesPage /></AdminRoute>} />
      <Route path="/admin/promotions" element={<AdminRoute><PromotionsPage /></AdminRoute>} />
      <Route path="/admin/parametres" element={<AdminRoute><SettingsPage /></AdminRoute>} />
      <Route
        path="/manager/dashboard"
        element={
          <RequireAuth role="gerante">
            <ManagerDashboard />
          </RequireAuth>
        }
      />
      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}

export default App
