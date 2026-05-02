import type { FormEvent } from 'react'

type LoginPanelProps = {
  identifier: string
  password: string
  showPassword: boolean
  rememberMe: boolean
  loading: boolean
  error: string | null
  onIdentifierChange: (value: string) => void
  onPasswordChange: (value: string) => void
  onToggleShowPassword: () => void
  onRememberChange: (checked: boolean) => void
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
}

function LoginPanel({
  identifier,
  password,
  showPassword,
  rememberMe,
  loading,
  error,
  onIdentifierChange,
  onPasswordChange,
  onToggleShowPassword,
  onRememberChange,
  onSubmit,
}: LoginPanelProps) {
  return (
    <section className="relative flex h-full flex-col items-center justify-start overflow-y-auto bg-[#fbf8f7] px-4 py-6 sm:px-8 sm:py-8 md:justify-center lg:px-12">
      <div className="w-full max-w-[440px] rounded-3xl bg-white p-6 shadow-[0_22px_60px_-35px_rgba(15,23,42,0.25)] sm:p-8">
        <div className="flex flex-col gap-3">
          <div>
            <h2 className="font-display text-2xl font-semibold text-gray-900 sm:text-3xl">
              Connexion
            </h2>
            <span className="mt-2 block h-0.5 w-10 rounded-full bg-[#e0245e]"></span>
          </div>
          <p className="text-xs text-gray-500 sm:text-sm">
            Connectez-vous pour accéder à votre espace
          </p>
        </div>

        {error && (
          <div
            className="mt-6 rounded-2xl border border-[#f5b6cb] bg-[#fff4f8] px-4 py-3 text-sm text-[#b0003a]"
            role="alert"
          >
            {error}
          </div>
        )}

        <form className="mt-6 flex flex-col gap-4" onSubmit={onSubmit}>
          <label className="flex flex-col gap-2 text-sm text-gray-600">
            Email ou téléphone
            <span className="relative">
              <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[#e0245e]">
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    d="M4 6.5C4 5.12 5.12 4 6.5 4H17.5C18.88 4 20 5.12 20 6.5V17.5C20 18.88 18.88 20 17.5 20H6.5C5.12 20 4 18.88 4 17.5V6.5Z"
                    stroke="currentColor"
                    strokeWidth="1.4"
                  />
                  <path
                    d="M4.8 6.8L11.07 11.08C11.63 11.48 12.37 11.48 12.93 11.08L19.2 6.8"
                    stroke="currentColor"
                    strokeWidth="1.4"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </span>
              <input
                type="text"
                inputMode="email"
                autoComplete="email"
                value={identifier}
                onChange={(event) => onIdentifierChange(event.target.value)}
                placeholder="Entrez votre email ou téléphone"
                className="w-full rounded-2xl border border-gray-200 bg-white py-3 pl-11 pr-4 text-sm text-gray-900 shadow-sm transition focus:border-[#e0245e] focus:outline-none focus:ring-2 focus:ring-[#f6c0d4]"
              />
            </span>
          </label>

          <label className="flex flex-col gap-2 text-sm text-gray-600">
            Mot de passe
            <span className="relative">
              <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[#e0245e]">
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    d="M6 10.5C6 8.02 8.02 6 10.5 6H13.5C15.98 6 18 8.02 18 10.5V18.5C18 19.88 16.88 21 15.5 21H8.5C7.12 21 6 19.88 6 18.5V10.5Z"
                    stroke="currentColor"
                    strokeWidth="1.4"
                  />
                  <path
                    d="M9 10V8.75C9 7.51 10.01 6.5 11.25 6.5H12.75C13.99 6.5 15 7.51 15 8.75V10"
                    stroke="currentColor"
                    strokeWidth="1.4"
                    strokeLinecap="round"
                  />
                </svg>
              </span>
              <input
                type={showPassword ? 'text' : 'password'}
                autoComplete="current-password"
                value={password}
                onChange={(event) => onPasswordChange(event.target.value)}
                placeholder="Entrez votre mot de passe"
                className="w-full rounded-2xl border border-gray-200 bg-white py-3 pl-11 pr-11 text-sm text-gray-900 shadow-sm transition focus:border-[#e0245e] focus:outline-none focus:ring-2 focus:ring-[#f6c0d4]"
              />
              <button
                type="button"
                onClick={onToggleShowPassword}
                className="absolute inset-y-0 right-3 flex items-center text-gray-400 transition hover:text-gray-600"
                aria-label={
                  showPassword
                    ? 'Masquer le mot de passe'
                    : 'Afficher le mot de passe'
                }
              >
                <svg
                  width="18"
                  height="18"
                  viewBox="0 0 24 24"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    d="M2 12C3.8 7.8 7.5 5 12 5C16.5 5 20.2 7.8 22 12C20.2 16.2 16.5 19 12 19C7.5 19 3.8 16.2 2 12Z"
                    stroke="currentColor"
                    strokeWidth="1.4"
                  />
                  <circle
                    cx="12"
                    cy="12"
                    r="3"
                    stroke="currentColor"
                    strokeWidth="1.4"
                  />
                </svg>
              </button>
            </span>
          </label>

          <div className="flex items-center justify-between gap-3 text-xs text-gray-600">
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={rememberMe}
                onChange={(event) => onRememberChange(event.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-[#e0245e] focus:ring-[#f6c0d4]"
              />
              Se souvenir de moi
            </label>
            <button
              type="button"
              className="text-xs font-medium text-[#e0245e] hover:text-[#c81f54]"
            >
              Mot de passe oublié ?
            </button>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="mt-2 inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-[#e0245e] px-5 py-3 text-sm font-semibold text-white shadow-[0_12px_25px_-15px_rgba(224,36,94,0.9)] transition hover:bg-[#c81f54] focus:outline-none focus:ring-2 focus:ring-[#f6c0d4] disabled:cursor-not-allowed disabled:bg-[#f3a7c4]"
          >
            {loading ? 'Connexion...' : 'Se connecter'}
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M5 12H19"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
              />
              <path
                d="M13 6L19 12L13 18"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </button>
        </form>

        <div className="mt-6 flex items-center gap-3 text-xs text-gray-300">
          <span className="h-px flex-1 bg-gray-200"></span>
          <span className="text-gray-400">ou continuer avec</span>
          <span className="h-px flex-1 bg-gray-200"></span>
        </div>

        <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <button
            type="button"
            className="inline-flex items-center justify-center gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:-translate-y-0.5 hover:border-gray-300"
          >
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M23.04 12.26C23.04 11.45 22.97 10.68 22.84 9.94H12V14.36H18.3C18.02 15.82 17.18 17.06 15.88 17.9V20.78H19.6C21.78 18.76 23.04 15.8 23.04 12.26Z"
                fill="#4285F4"
              />
              <path
                d="M12 23.5C15.1 23.5 17.7 22.47 19.6 20.78L15.88 17.9C14.85 18.6 13.54 19.01 12 19.01C9.01 19.01 6.47 16.99 5.57 14.24H1.74V17.22C3.63 20.98 7.52 23.5 12 23.5Z"
                fill="#34A853"
              />
              <path
                d="M5.57 14.24C5.34 13.54 5.21 12.8 5.21 12.03C5.21 11.26 5.34 10.52 5.57 9.82V6.84H1.74C0.97 8.34 0.53 10.04 0.53 12.03C0.53 14.02 0.97 15.72 1.74 17.22L5.57 14.24Z"
                fill="#FBBC05"
              />
              <path
                d="M12 5.05C13.69 5.05 15.21 5.63 16.39 6.76L19.68 3.47C17.69 1.62 15.1 0.56 12 0.56C7.52 0.56 3.63 3.08 1.74 6.84L5.57 9.82C6.47 7.07 9.01 5.05 12 5.05Z"
                fill="#EA4335"
              />
            </svg>
            Continuer avec Google
          </button>
          <button
            type="button"
            className="inline-flex items-center justify-center gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:-translate-y-0.5 hover:border-gray-300"
          >
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path d="M3 3H12V12H3V3Z" fill="#F25022" />
              <path d="M12 3H21V12H12V3Z" fill="#7FBA00" />
              <path d="M3 12H12V21H3V12Z" fill="#00A4EF" />
              <path d="M12 12H21V21H12V12Z" fill="#FFB900" />
            </svg>
            Continuer avec Microsoft
          </button>
        </div>

        <div className="mt-6 flex items-center justify-center gap-2 rounded-2xl bg-[#fdf2f6] px-4 py-3 text-[11px] text-gray-600">
          <span className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white text-[#e0245e] shadow-sm">
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                d="M6 11V9C6 6.24 8.24 4 11 4H13C15.76 4 18 6.24 18 9V11"
                stroke="currentColor"
                strokeWidth="1.4"
                strokeLinecap="round"
              />
              <rect
                x="5"
                y="11"
                width="14"
                height="9"
                rx="2.5"
                stroke="currentColor"
                strokeWidth="1.4"
              />
            </svg>
          </span>
          <span>Accès sécurisé et protégé · Vos données sont 100% sécurisées</span>
        </div>
      </div>

      <footer className="mt-6 text-center text-[11px] text-gray-400 sm:text-xs">
        <p>© 2026 Bichette Thomas Salon de Coiffure. Tous droits réservés.</p>
        <div className="mt-1 flex items-center justify-center gap-4">
          <a href="#" className="font-medium text-gray-400 hover:text-gray-500">
            Conditions d'utilisation
          </a>
          <a href="#" className="font-medium text-gray-400 hover:text-gray-500">
            Confidentialité
          </a>
        </div>
      </footer>
    </section>
  )
}

export default LoginPanel
