export type User = {
  id: number
  name: string
  email: string
  role: 'admin' | 'gerante' | string | null
}

export type LoginResponse = {
  message: string
  token_type: string
  access_token: string
  user: User
}
