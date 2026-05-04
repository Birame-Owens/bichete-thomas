import type { ReactNode } from 'react'

export const inputClass =
  'w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-900 outline-none transition focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10'

export const primaryButtonClass =
  'rounded-lg bg-[#e91e63] px-4 py-2.5 text-sm font-black text-white shadow-[0_14px_24px_-18px_rgba(233,30,99,0.9)] transition hover:bg-[#c41468] disabled:cursor-not-allowed disabled:opacity-60'

export const secondaryButtonClass =
  'rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-black text-gray-600 transition hover:border-[#e91e63] hover:text-[#c41468] disabled:cursor-not-allowed disabled:opacity-50'

export const dangerButtonClass =
  'rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm font-black text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60'

export function FormField({
  label,
  children,
  hint,
}: {
  label: string
  children: ReactNode
  hint?: string
}) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-xs font-black uppercase tracking-[0.12em] text-gray-500">
        {label}
      </span>
      {children}
      {hint && <span className="mt-1.5 block text-xs font-semibold text-gray-400">{hint}</span>}
    </label>
  )
}

export function Modal({
  title,
  children,
  onClose,
  wide = false,
}: {
  title: string
  children: ReactNode
  onClose: () => void
  wide?: boolean
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/45 px-2 py-2 sm:items-center sm:px-4 sm:py-6">
      <section
        className={`max-h-[95vh] w-full overflow-y-auto rounded-xl bg-white p-4 shadow-2xl sm:max-h-[92vh] sm:p-5 ${
          wide ? 'max-w-6xl' : 'max-w-3xl'
        }`}
      >
        <div className="mb-5 flex items-center justify-between gap-4">
          <h2 className="text-xl font-black text-[#111018]">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full bg-gray-100 px-3 py-1.5 text-sm font-black text-gray-600 transition hover:bg-gray-200"
          >
            Fermer
          </button>
        </div>
        {children}
      </section>
    </div>
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

export function SuccessState({ label }: { label: string }) {
  return (
    <div className="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">
      {label}
    </div>
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
      <span className="text-sm font-bold text-gray-500">{total.toLocaleString('fr-FR')} reservation(s)</span>
      <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 sm:flex">
        <button type="button" onClick={onPrevious} disabled={page <= 1} className={`${secondaryButtonClass} justify-center`}>
          Precedent
        </button>
        <span className="rounded-lg bg-[#fff2f7] px-3 py-2 text-center text-sm font-black text-[#c41468]">
          {page} / {lastPage}
        </span>
        <button type="button" onClick={onNext} disabled={page >= lastPage} className={`${secondaryButtonClass} justify-center`}>
          Suivant
        </button>
      </div>
    </div>
  )
}
