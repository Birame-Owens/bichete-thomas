// Charge les matchers jest-dom (toBeInTheDocument, toBeDisabled, ...) pour
// Vitest, et nettoie le DOM rendu entre chaque test.
import '@testing-library/jest-dom/vitest'
import { afterEach } from 'vitest'
import { cleanup } from '@testing-library/react'

afterEach(() => {
  cleanup()
})
