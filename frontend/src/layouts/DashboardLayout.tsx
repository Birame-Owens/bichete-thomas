import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { logout } from '../services/authService'
import { clearAuth, getToken } from '../lib/authStorage'

type DashboardLayoutProps = {
  title: string
  subtitle: string
}

function DashboardLayout({ title, subtitle }: DashboardLayoutProps) {
  const navigate = useNavigate()
  const [loggingOutCurrent, setLoggingOutCurrent] = useState(false)
  const [loggingOutAll, setLoggingOutAll] = useState(false)
  const [logoutError, setLogoutError] = useState<string | null>(null)

  const handleLogout = async (scope: 'current' | 'all') => {
    const token = getToken()
    setLogoutError(null)

    if (!token) {
      clearAuth()
      navigate('/login', { replace: true })
      return
    }

    if (scope === 'current') {
      setLoggingOutCurrent(true)
    } else {
      setLoggingOutAll(true)
    }

    try {
      await logout(token, scope)
    } catch (logoutRequestError) {
      setLogoutError('La deconnexion a echoue. Reessayez ou reconnectez-vous.')
    } finally {
      clearAuth()
      setLoggingOutCurrent(false)
      setLoggingOutAll(false)
      navigate('/login', { replace: true })
    }
  }

  return (
    <div className="min-h-screen bg-[#f6f5f4]">
      <header className="border-b border-gray-100 bg-white/90 backdrop-blur">
        <div className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-4">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.35em] text-[#e0245e]">
              Bichette Thomas
            </p>
            <h1 className="font-display text-2xl text-gray-900">{title}</h1>
            <p className="text-sm text-gray-500">{subtitle}</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              onClick={() => handleLogout('current')}
              disabled={loggingOutCurrent || loggingOutAll}
              className="inline-flex items-center justify-center rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-gray-300 hover:text-gray-900 disabled:cursor-not-allowed disabled:text-gray-400"
            >
              {loggingOutCurrent ? 'Deconnexion...' : 'Se deconnecter'}
            </button>
            <button
              type="button"
              onClick={() => handleLogout('all')}
              disabled={loggingOutCurrent || loggingOutAll}
              className="inline-flex items-center justify-center rounded-full border border-[#f2c6d7] bg-[#fff4f8] px-4 py-2 text-sm font-semibold text-[#c81f54] shadow-sm transition hover:border-[#e9a7c1] hover:text-[#a81645] disabled:cursor-not-allowed disabled:text-[#d58aa8]"
            >
              {loggingOutAll ? 'Deconnexion...' : 'Deconnexion partout'}
            </button>
          </div>
        </div>
        {logoutError && (
          <p className="mx-auto w-full max-w-5xl px-6 pb-4 text-sm text-[#b0003a]">
            {logoutError}
          </p>
        )}
      </header>
      <div className="mx-auto w-full max-w-5xl px-6 py-12">
        <div className="rounded-3xl border border-gray-100 bg-white p-10 shadow-sm">
          <h2 className="font-display text-3xl text-gray-900">{title}</h2>
          <p className="mt-3 text-gray-600">{subtitle}</p>
        </div>
      </div>
    </div>
  )
}

export default DashboardLayout
