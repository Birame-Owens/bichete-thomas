export const inputClass =
  'w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-900 outline-none transition focus:border-[#e91e63] focus:ring-4 focus:ring-[#e91e63]/10'

export const secondaryButtonClass =
  'rounded-lg border border-gray-200 px-3 py-2 text-sm font-black text-gray-600 transition hover:border-[#e91e63] hover:text-[#c41468] disabled:cursor-not-allowed disabled:opacity-50'

export const dangerButtonClass =
  'rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm font-black text-red-700 transition hover:bg-red-100'

export const primaryButtonClass =
  'rounded-lg bg-[#e91e63] px-4 py-2.5 text-sm font-black text-white shadow-[0_14px_24px_-18px_rgba(233,30,99,0.9)] transition hover:bg-[#c41468] disabled:cursor-not-allowed disabled:opacity-60'

export function money(value: number | string) {
  return `${Number(value).toLocaleString('fr-FR')} FCFA`
}
