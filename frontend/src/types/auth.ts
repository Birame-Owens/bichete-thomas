export type User = {
  id: number
  name: string
  email: string
  role: 'admin' | 'gerante' | string | null
}

export type LoginResponse = {
  message: string
  user: User
}
