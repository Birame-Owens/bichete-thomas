import type { ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { getToken, getUser } from '../../lib/authStorage'

type RequireAuthProps = {
  children: ReactNode
  role?: 'admin' | 'gerante' | 'client'
}

function RequireAuth({ children, role }: RequireAuthProps) {
  const token = getToken()
  const user = getUser()

  if (!token) {
    return <Navigate to="/login" replace />
  }

  if (role && user?.role !== role) {
    return <Navigate to="/login" replace />
  }

  return children
}

export default RequireAuth
