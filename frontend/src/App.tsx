import { Navigate, Route, Routes } from 'react-router-dom'
import LoginPage from './pages/LoginPage'
import AdminDashboardPage from './pages/AdminDashboardPage'
import DashboardLayout from './layouts/DashboardLayout'

function ManagerDashboard() {
  return (
    <DashboardLayout
      title="Manager dashboard"
      subtitle="Vous etes connecte en tant que gerante."
    />
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
      <Route path="/admin/dashboard" element={<AdminDashboardPage />} />
      <Route path="/manager/dashboard" element={<ManagerDashboard />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}

export default App
