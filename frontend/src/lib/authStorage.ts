import type { User } from '../types/auth'

const USER_KEY = 'auth_user'
const REMEMBER_KEY = 'remember_me'

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

export const setUser = (user: User) => {
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

export const setRememberMe = (remember: boolean) => {
  localStorage.setItem(REMEMBER_KEY, String(remember))
}

export const clearAuth = () => {
  localStorage.removeItem(USER_KEY)
  localStorage.removeItem(REMEMBER_KEY)
}
