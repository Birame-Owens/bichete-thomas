import type { ReactNode } from 'react'

type AuthLayoutProps = {
  left: ReactNode
  right: ReactNode
}

function AuthLayout({ left, right }: AuthLayoutProps) {
  return (
    <div className="h-[100svh] w-[100vw] overflow-hidden bg-[#f7f4f2]">
      <div className="grid h-full grid-cols-1 grid-rows-[auto_1fr] md:grid-cols-[0.45fr_0.55fr] md:grid-rows-1">
        {left}
        {right}
      </div>
    </div>
  )
}

export default AuthLayout
