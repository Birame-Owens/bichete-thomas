import type { ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { getUser } from '../../lib/authStorage'

type RequireAuthProps = {
  children: ReactNode
  role?: 'admin' | 'gerante' | 'client'
}

function RequireAuth({ children, role }: RequireAuthProps) {
  const user = getUser()

  if (!user) {
    return <Navigate to="/login" replace />
  }

  if (role && user.role !== role) {
    return <Navigate to="/login" replace />
  }

  return children
}

export default RequireAuth
