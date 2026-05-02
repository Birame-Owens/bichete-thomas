import type { ReactNode } from 'react'
import { secondaryButtonClass } from './catalogueUiTokens'

export function Panel({
  title,
  subtitle,
  children,
  action,
  className = '',
}: {
  title: string
  subtitle?: string
  children: ReactNode
  action?: ReactNode
  className?: string
}) {
  return (
    <section className={`rounded-xl border border-[#f1e7ee] bg-white p-5 shadow-[0_16px_34px_-30px_rgba(20,20,43,0.5)] ${className}`}>
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-xl font-black text-[#111018]">{title}</h2>
          {subtitle && <p className="mt-1 text-sm font-medium text-gray-500">{subtitle}</p>}
        </div>
        {action}
      </div>
      {children}
    </section>
  )
}

export function Modal({
  title,
  children,
  onClose,
}: {
  title: string
  children: ReactNode
  onClose: () => void
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/45 px-4 py-6">
      <section className="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white p-5 shadow-2xl">
        <div className="mb-5 flex items-center justify-between gap-4">
          <h2 className="text-xl font-black text-[#111018]">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full bg-gray-100 px-3 py-1.5 text-sm font-black text-gray-600"
          >
            Fermer
          </button>
        </div>
        {children}
      </section>
    </div>
  )
}

export function StatusBadge({ active }: { active: boolean }) {
  return (
    <span
      className={`inline-flex rounded-full px-3 py-1 text-xs font-black ${
        active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'
      }`}
    >
      {active ? 'Actif' : 'Inactif'}
    </span>
  )
}

export function EmptyState({ label }: { label: string }) {
  return (
    <div className="flex min-h-[170px] items-center justify-center rounded-xl border border-dashed border-[#efc7d8] bg-[#fff8fb] px-5 text-center text-sm font-bold text-[#a91550]">
      {label}
    </div>
  )
}

export function ErrorState({ label }: { label: string }) {
  return (
    <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
      {label}
    </div>
  )
}

export function FormField({
  label,
  children,
}: {
  label: string
  children: ReactNode
}) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-gray-500">
        {label}
      </span>
      {children}
    </label>
  )
}

export function Pagination({
  page,
  lastPage,
  total,
  onPrevious,
  onNext,
}: {
  page: number
  lastPage: number
  total: number
  onPrevious: () => void
  onNext: () => void
}) {
  return (
    <div className="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
      <span className="text-sm font-bold text-gray-500">{total.toLocaleString('fr-FR')} elements</span>
      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={onPrevious}
          disabled={page <= 1}
          className={secondaryButtonClass}
        >
          Precedent
        </button>
        <span className="rounded-lg bg-[#fff2f7] px-3 py-2 text-sm font-black text-[#c41468]">
          {page} / {lastPage}
        </span>
        <button
          type="button"
          onClick={onNext}
          disabled={page >= lastPage}
          className={secondaryButtonClass}
        >
          Suivant
        </button>
      </div>
    </div>
  )
}
