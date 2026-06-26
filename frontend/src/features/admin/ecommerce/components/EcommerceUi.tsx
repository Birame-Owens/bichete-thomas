import type { ReactNode } from 'react'
import { ChevronLeft, ChevronRight, X } from 'lucide-react'

export const inputClass =
  'w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5] focus:border-transparent'

export const primaryButtonClass =
  'inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-[#e91e63] text-white font-medium hover:bg-[#d81b60] focus:outline-none focus:ring-2 focus:ring-[#ff5ca5] focus:ring-offset-2 transition-colors'

export const secondaryButtonClass =
  'inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium hover:bg-gray-50 hover:border-[#ff5ca5] focus:outline-none focus:ring-2 focus:ring-[#ff5ca5] focus:ring-offset-2 transition-colors'

export const dangerButtonClass =
  'inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-red-500 text-white font-medium hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300 focus:ring-offset-2 transition-colors'

export function money(value: number): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
  }).format(value)
}

export function Panel({
  title,
  subtitle,
  action,
  children,
}: {
  title: string
  subtitle?: string
  action?: ReactNode
  children: ReactNode
}) {
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-gray-900">{title}</h2>
          {subtitle && <p className="text-sm text-gray-600 mt-1">{subtitle}</p>}
        </div>
        {action && <div>{action}</div>}
      </div>
      {children}
    </div>
  )
}

export function Modal({
  isOpen,
  title,
  onClose,
  children,
}: {
  isOpen: boolean
  title: string
  onClose: () => void
  children: ReactNode
}) {
  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="relative w-full max-w-2xl max-h-[90vh] bg-white rounded-lg shadow-xl overflow-hidden">
        {/* Header */}
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4">
          <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
          <button
            onClick={onClose}
            className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X size={20} />
          </button>
        </div>

        {/* Content */}
        <div className="overflow-y-auto max-h-[calc(90vh-100px)]">{children}</div>
      </div>
    </div>
  )
}

export function FormField({
  label,
  error,
  children,
}: {
  label: string
  error?: string
  children: ReactNode
}) {
  return (
    <div className="space-y-2">
      <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wide">{label}</label>
      {children}
      {error && <p className="text-sm text-red-500">{error}</p>}
    </div>
  )
}

export function StatusBadge({ status, label }: { status: string; label: string }) {
  const statusConfig: Record<string, { bg: string; text: string }> = {
    active: { bg: 'bg-green-100', text: 'text-green-700' },
    inactive: { bg: 'bg-gray-100', text: 'text-gray-700' },
    en_attente: { bg: 'bg-yellow-100', text: 'text-yellow-700' },
    confirmee: { bg: 'bg-blue-100', text: 'text-blue-700' },
    en_preparation: { bg: 'bg-indigo-100', text: 'text-indigo-700' },
    en_production: { bg: 'bg-purple-100', text: 'text-purple-700' },
    prete: { bg: 'bg-cyan-100', text: 'text-cyan-700' },
    en_livraison: { bg: 'bg-violet-100', text: 'text-violet-700' },
    livree: { bg: 'bg-green-100', text: 'text-green-700' },
    annulee: { bg: 'bg-red-100', text: 'text-red-700' },
    echoue: { bg: 'bg-red-100', text: 'text-red-700' },
    retournee: { bg: 'bg-orange-100', text: 'text-orange-700' },
  }

  const config = statusConfig[status] || statusConfig.inactive

  return <span className={`inline-block px-2.5 py-1 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>{label}</span>
}

export function EmptyState({ title, description }: { title: string; description?: string }) {
  return (
    <div className="rounded-lg border-2 border-dashed border-gray-300 px-6 py-12 text-center">
      <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
      {description && <p className="mt-2 text-sm text-gray-600">{description}</p>}
    </div>
  )
}

export function ErrorState({ message }: { message: string }) {
  return (
    <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 mb-4">
      <strong>Erreur:</strong> {message}
    </div>
  )
}

export function SuccessState({ message }: { message: string }) {
  return (
    <div className="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 mb-4">
      ✓ {message}
    </div>
  )
}

export function Pagination({
  currentPage,
  lastPage,
  onPageChange,
  total,
  perPage,
}: {
  currentPage: number
  lastPage: number
  onPageChange: (page: number) => void
  total: number
  perPage: number
}) {
  return (
    <div className="flex items-center justify-between py-4">
      <div className="text-sm text-gray-600">
        Affichage {(currentPage - 1) * perPage + 1} à {Math.min(currentPage * perPage, total)} sur {total}
      </div>
      <div className="flex gap-2">
        <button
          onClick={() => onPageChange(Math.max(1, currentPage - 1))}
          disabled={currentPage === 1}
          className="p-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronLeft size={18} />
        </button>
        <div className="flex items-center gap-1 px-3">
          <span className="text-sm font-medium text-gray-700">
            Page {currentPage} / {lastPage}
          </span>
        </div>
        <button
          onClick={() => onPageChange(Math.min(lastPage, currentPage + 1))}
          disabled={currentPage === lastPage}
          className="p-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <ChevronRight size={18} />
        </button>
      </div>
    </div>
  )
}
