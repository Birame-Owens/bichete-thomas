import type { User } from '../types/auth'

// Keep localStorage key names in one place for easy changes.
const TOKEN_KEY = 'auth_token'
const USER_KEY = 'auth_user'
const REMEMBER_KEY = 'remember_me'

// Read the current access token if it exists.
export const getToken = () => localStorage.getItem(TOKEN_KEY)

export const getUser = (): User | null => {
  const rawUser = localStorage.getItem(USER_KEY)

  if (!rawUser) {
    return null
  }

  try {
    return JSON.parse(rawUser) as User
  } catch {
    return null
  }
}

// Persist access token for authenticated requests.
export const setToken = (token: string) => {
  localStorage.setItem(TOKEN_KEY, token)
}

// Persist the authenticated user to reuse across reloads.
export const setUser = (user: User) => {
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

// Store user preference for remember-me behavior.
export const setRememberMe = (remember: boolean) => {
  localStorage.setItem(REMEMBER_KEY, String(remember))
}

// Clear all auth-related storage values on logout.
export const clearAuth = () => {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
  localStorage.removeItem(REMEMBER_KEY)
}
