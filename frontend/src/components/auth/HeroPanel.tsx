import heroPhoto from '../../assets/hero.jpg'

function HeroPanel() {
  return (
    <section className="relative h-44 overflow-hidden sm:h-56 md:h-full">
      <img
        src={heroPhoto}
        alt="Ambiance du salon Bichette Thomas"
        className="absolute inset-0 h-full w-full object-cover"
      />
      <div className="absolute inset-0 bg-gradient-to-br from-black/30 via-transparent to-white/20"></div>
      <div className="absolute left-6 top-6 hidden items-center gap-3 rounded-2xl bg-white/80 px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm backdrop-blur md:flex">
        <span className="flex h-9 w-9 items-center justify-center rounded-full bg-[#f6d4df] text-[#e0245e]">
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M12 3C8.13 3 5 5.69 5 9.01C5 11.48 6.41 13.04 8.12 14.36L7.6 20.5L12 18.21L16.4 20.5L15.88 14.36C17.59 13.04 19 11.48 19 9.01C19 5.69 15.87 3 12 3Z"
              stroke="currentColor"
              strokeWidth="1.3"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </span>
        <div className="leading-tight">
          <p className="text-[11px] uppercase tracking-[0.2em] text-[#e0245e]">
            Bichette
          </p>
          <p className="text-sm font-semibold">Thomas</p>
          <p className="text-[10px] text-gray-500">Salon de Coiffure</p>
        </div>
      </div>
      <div className="absolute bottom-5 left-1/2 w-[88%] max-w-md -translate-x-1/2 rounded-3xl border border-white/40 bg-white/20 p-4 text-white shadow-[0_20px_45px_-25px_rgba(0,0,0,0.6)] backdrop-blur-lg sm:bottom-6 sm:p-5 md:left-10 md:w-[78%] md:translate-x-0">
        <p className="text-base font-semibold text-white sm:text-lg">
          Sublimez votre beauté,
          <span className="block text-white/90">révélez votre confiance</span>
        </p>
        <p className="mt-2 text-xs text-white/85">
          Gérez votre salon en toute simplicité avec Bichette Thomas.
        </p>
        <div className="mt-4 flex items-start justify-between gap-3 text-center">
          <div className="flex flex-1 flex-col items-center gap-2">
            <span className="rounded-2xl bg-white/15 p-2">
              <svg
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <rect
                  x="4"
                  y="5"
                  width="16"
                  height="15"
                  rx="3"
                  stroke="currentColor"
                  strokeWidth="1.4"
                />
                <path
                  d="M8 3V7"
                  stroke="currentColor"
                  strokeWidth="1.4"
                  strokeLinecap="round"
                />
                <path
                  d="M16 3V7"
                  stroke="currentColor"
                  strokeWidth="1.4"
                  strokeLinecap="round"
                />
                <path
                  d="M4 10H20"
                  stroke="currentColor"
                  strokeWidth="1.4"
                  strokeLinecap="round"
                />
              </svg>
            </span>
            <span className="text-[10px] font-medium sm:text-xs">Réservations</span>
          </div>
          <div className="flex flex-1 flex-col items-center gap-2">
            <span className="rounded-2xl bg-white/15 p-2">
              <svg
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  d="M6 4H18"
                  stroke="currentColor"
                  strokeWidth="1.4"
                  strokeLinecap="round"
                />
                <path
                  d="M6 10H18"
                  stroke="currentColor"
                  strokeWidth="1.4"
                  strokeLinecap="round"
                />
                <path
                  d="M6 16H14"
                  stroke="currentColor"
                  strokeWidth="1.4"
                  strokeLinecap="round"
                />
              </svg>
            </span>
            <span className="text-[10px] font-medium sm:text-xs">Planning</span>
          </div>
          <div className="flex flex-1 flex-col items-center gap-2">
            <span className="rounded-2xl bg-white/15 p-2">
              <svg
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  d="M12 4L14.7 9.2L20.5 9.8L16.2 13.6L17.4 19.4L12 16.5L6.6 19.4L7.8 13.6L3.5 9.8L9.3 9.2L12 4Z"
                  stroke="currentColor"
                  strokeWidth="1.4"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </svg>
            </span>
            <span className="text-[10px] font-medium sm:text-xs">Statistiques</span>
          </div>
        </div>
      </div>
    </section>
  )
}

export default HeroPanel
