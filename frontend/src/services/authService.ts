import type { LoginResponse } from '../types/auth'
import { apiClient } from '../lib/apiClient'

export const login = async (
  email: string,
  password: string,
  deviceName: string
) => {
  const response = await apiClient.post<LoginResponse>('/auth/login', {
    email,
    password,
    device_name: deviceName,
  })

  return response.data
}

export const logout = async (scope: 'current' | 'all') => {
  await apiClient.post(`/auth/${scope === 'current' ? 'logout' : 'logout-all'}`)
}
