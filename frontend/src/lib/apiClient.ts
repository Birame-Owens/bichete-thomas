import axios from 'axios'
import { getToken } from './authStorage'

// Base URL is configurable via VITE_API_BASE_URL for different environments.
export const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api'

// Shared Axios instance to standardize headers and base URL.
export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
})

apiClient.interceptors.request.use((config) => {
  const token = getToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})
