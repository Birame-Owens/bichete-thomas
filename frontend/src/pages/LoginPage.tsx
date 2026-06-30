import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import AuthLayout from '../layouts/AuthLayout'
import HeroPanel from '../components/auth/HeroPanel'
import LoginPanel from '../components/auth/LoginPanel'
import { login } from '../services/authService'
import {
  getUser,
  setRememberMe as persistRememberMe,
  setUser,
} from '../lib/authStorage'

function LoginPage() {
  const navigate = useNavigate()

  // Déjà connecté : évite d'afficher le formulaire inutilement
  useEffect(() => {
    const user = getUser()
    if (user?.role === 'admin') navigate('/console-thomas/dashboard', { replace: true })
    else if (user?.role === 'gerante') navigate('/manager/reservations', { replace: true })
  }, [navigate])

  const [identifier, setIdentifier] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [rememberMe, setRememberMe] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)

    if (!identifier.trim() || !password.trim()) {
      setError('Veuillez renseigner votre identifiant et votre mot de passe.')
      return
    }

    setLoading(true)

    try {
      const response = await login(identifier.trim(), password, 'frontend')

      setUser(response.user)
      persistRememberMe(rememberMe)

      if (response.user.role === 'admin') {
        navigate('/console-thomas/dashboard')
        return
      }

      if (response.user.role === 'gerante') {
        navigate('/manager/dashboard')
        return
      }

      navigate('/')
    } catch (err: any) {
      console.error('Login error:', err)
      const errorMsg = err?.response?.data?.message || 'Impossible de vous connecter. Verifiez vos identifiants.'
      setError(errorMsg)
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthLayout
      left={<HeroPanel />}
      right={
        <LoginPanel
          identifier={identifier}
          password={password}
          showPassword={showPassword}
          rememberMe={rememberMe}
          loading={loading}
          error={error}
          onIdentifierChange={setIdentifier}
          onPasswordChange={setPassword}
          onToggleShowPassword={() => setShowPassword((value) => !value)}
          onRememberChange={setRememberMe}
          onSubmit={handleSubmit}
        />
      }
    />
  )
}

export default LoginPage
