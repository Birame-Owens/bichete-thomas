import { Navigate, Route, Routes } from 'react-router-dom'
import LoginPage from './pages/LoginPage'
import AdminDashboardPage from './features/admin/dashboard/AdminDashboardPage'
import RequireAuth from './features/auth/RequireAuth'

function ManagerDashboard() {
  return (
    <div className="min-h-screen bg-white px-6 py-12">
      <h1 className="text-3xl font-bold">Espace gerante</h1>
    </div>
  )
}

function NotFound() {
  return (
    <div className="min-h-screen bg-white px-6 py-12">
      <div className="mx-auto w-full max-w-3xl rounded-3xl border border-gray-100 bg-white p-10 shadow-sm">
        <h1 className="font-display text-3xl text-gray-900">Page introuvable</h1>
        <p className="mt-3 text-gray-600">
          La page demandee n existe pas.
        </p>
      </div>
    </div>
  )
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
          <RequireAuth role="admin">
            <AdminDashboardPage />
          </RequireAuth>
        }
      />
      <Route path="/manager/dashboard" element={<ManagerDashboard />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}

export default App
