import type { LoginResponse } from '../types/auth'
import { apiClient } from '../lib/apiClient'

export const login = async (
  email: string,
  password: string,
  deviceName: string
) => {
  // Centralized login request to keep API calls consistent across the app.
  const response = await apiClient.post<LoginResponse>('/auth/login', {
    email,
    password,
    device_name: deviceName,
  })

  return response.data
}

export const logout = async (token: string, scope: 'current' | 'all') => {
  // Use the appropriate endpoint for single-session vs. global logout.
  await apiClient.post(
    `/auth/${scope === 'current' ? 'logout' : 'logout-all'}`,
    {},
    {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    }
  )
}
