import { lazy, Suspense, type ReactNode } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'

// Routes "publiques" (page client, login) : import direct - elles font partie
// du parcours principal et le visiteur du catalogue les charge de toute facon.
import LoginPage from './pages/LoginPage'
import ClientHomePage from './features/client/ClientHomePage'
import ClientCategoryPage from './features/client/ClientCategoryPage'
import AvisPage from './features/client/AvisPage'
import RequireAuth from './features/auth/RequireAuth'

// Routes admin (I9) : code-split via React.lazy + Suspense.
// Avant : toutes les pages admin (17 chunks ~200 kB gzippes au total) etaient
// dans le bundle initial. Un visiteur du catalogue telechargeait inutilement
// le code admin qu il ne verra jamais.
// Maintenant : chaque page admin est un chunk separe, charge a la demande
// quand l administratrice navigue dessus. Bundle initial client allege de
// ~100-200 kB gzipped, premier rendu plus rapide (critique en mobile/3G).
const AdminDashboardPage = lazy(() => import('./features/admin/dashboard/AdminDashboardPage'))
const ClientsPage = lazy(() => import('./features/admin/clients/ClientsPage'))
const ReservationsPage = lazy(() => import('./features/admin/reservations/ReservationsPage'))
const PaymentsPage = lazy(() => import('./features/admin/payments/PaymentsPage'))
const ExpensesPage = lazy(() => import('./features/admin/expenses/ExpensesPage'))
const ReportsPage = lazy(() => import('./features/admin/reports/ReportsPage'))
const ReviewsPage = lazy(() => import('./features/admin/reviews/ReviewsPage'))
const CatalogueOverviewPage = lazy(() => import('./features/admin/catalogue/CatalogueOverviewPage'))
const CategoriesCoiffuresPage = lazy(() => import('./features/admin/catalogue/CategoriesCoiffuresPage'))
const CoiffuresPage = lazy(() => import('./features/admin/catalogue/CoiffuresPage'))
const OptionsCoiffuresPage = lazy(() => import('./features/admin/catalogue/OptionsCoiffuresPage'))
const VariantesCoiffuresPage = lazy(() => import('./features/admin/catalogue/VariantesCoiffuresPage'))
const PersonnelOverviewPage = lazy(() => import('./features/admin/personnel/PersonnelOverviewPage'))
const GerantesPage = lazy(() => import('./features/admin/personnel/GerantesPage'))
const CoiffeusesPage = lazy(() => import('./features/admin/personnel/CoiffeusesPage'))
const SettingsPage = lazy(() => import('./features/admin/settings/SettingsPage'))
const PromotionsPage = lazy(() => import('./features/admin/promotions/PromotionsPage'))
const LogsPage = lazy(() => import('./features/admin/logs-systeme/LogsPage'))
const GeranteReservationsPage = lazy(() => import('./features/gerante/GeranteReservationsPage'))
const GeranteClientsPage = lazy(() => import('./features/gerante/GeranteClientsPage'))
const GerantePaiementsPage = lazy(() => import('./features/gerante/GerantePaiementsPage'))

function NotFound() {
  return (
    <div className="min-h-screen bg-white px-6 py-12">
      <div className="mx-auto w-full max-w-3xl rounded-xl border border-gray-100 bg-white p-10 shadow-sm">
        <h1 className="font-display text-3xl text-gray-900">Page introuvable</h1>
        <p className="mt-3 text-gray-600">
          La page demandee n existe pas.
        </p>
      </div>
    </div>
  )
}

// Fallback affiche pendant le chargement d un chunk admin paresseux.
// Volontairement minimaliste : la page entiere n est pas encore montee, on
// donne juste un retour visuel discret (3 ms a peine sur reseau rapide,
// jusqu a quelques centaines de ms en 3G).
function RouteSuspenseFallback() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-[#faf9fa]">
      <div className="text-sm font-semibold text-gray-500">Chargement...</div>
    </div>
  )
}

function AdminRoute({ children }: { children: ReactNode }) {
  // Wrap dans Suspense pour gerer le chunk lazy en cours de chargement.
  return (
    <RequireAuth role="admin">
      <Suspense fallback={<RouteSuspenseFallback />}>{children}</Suspense>
    </RequireAuth>
  )
}

function App() {
  return (
    <Routes>
      <Route path="/" element={<ClientHomePage />} />
      <Route path="/client" element={<ClientHomePage />} />
      <Route path="/categories" element={<ClientCategoryPage />} />
      <Route path="/categories/:categoryId" element={<ClientCategoryPage />} />
      <Route path="/avis/:token" element={<AvisPage />} />
      <Route path="/console-thomas" element={<LoginPage />} />
      <Route path="/console-thomas/dashboard" element={<AdminRoute><AdminDashboardPage /></AdminRoute>} />
      <Route path="/console-thomas/clients" element={<AdminRoute><ClientsPage /></AdminRoute>} />
      <Route path="/console-thomas/reservations" element={<AdminRoute><ReservationsPage /></AdminRoute>} />
      <Route path="/console-thomas/paiements" element={<AdminRoute><PaymentsPage /></AdminRoute>} />
      <Route path="/console-thomas/depenses" element={<AdminRoute><ExpensesPage /></AdminRoute>} />
      <Route path="/console-thomas/rapports" element={<AdminRoute><ReportsPage /></AdminRoute>} />
      <Route path="/console-thomas/avis" element={<AdminRoute><ReviewsPage /></AdminRoute>} />
      <Route path="/console-thomas/catalogue" element={<AdminRoute><CatalogueOverviewPage /></AdminRoute>} />
      <Route path="/console-thomas/catalogue/categories-coiffures" element={<AdminRoute><CategoriesCoiffuresPage /></AdminRoute>} />
      <Route path="/console-thomas/catalogue/coiffures" element={<AdminRoute><CoiffuresPage /></AdminRoute>} />
      <Route path="/console-thomas/catalogue/variantes" element={<AdminRoute><VariantesCoiffuresPage /></AdminRoute>} />
      <Route path="/console-thomas/catalogue/options" element={<AdminRoute><OptionsCoiffuresPage /></AdminRoute>} />
      <Route path="/console-thomas/personnel" element={<AdminRoute><PersonnelOverviewPage /></AdminRoute>} />
      <Route path="/console-thomas/personnel/gerantes" element={<AdminRoute><GerantesPage /></AdminRoute>} />
      <Route path="/console-thomas/personnel/coiffeuses" element={<AdminRoute><CoiffeusesPage /></AdminRoute>} />
      <Route path="/console-thomas/promotions" element={<AdminRoute><PromotionsPage /></AdminRoute>} />
      <Route path="/console-thomas/logs" element={<AdminRoute><LogsPage /></AdminRoute>} />
      <Route path="/console-thomas/parametres" element={<AdminRoute><SettingsPage /></AdminRoute>} />
      <Route path="/manager" element={<Navigate to="/manager/reservations" replace />} />
      <Route path="/manager/dashboard" element={<Navigate to="/manager/reservations" replace />} />
      <Route
        path="/manager/reservations"
        element={
          <RequireAuth role="gerante">
            <Suspense fallback={<RouteSuspenseFallback />}>
              <GeranteReservationsPage />
            </Suspense>
          </RequireAuth>
        }
      />
      <Route
        path="/manager/clients"
        element={
          <RequireAuth role="gerante">
            <Suspense fallback={<RouteSuspenseFallback />}>
              <GeranteClientsPage />
            </Suspense>
          </RequireAuth>
        }
      />
      <Route
        path="/manager/paiements"
        element={
          <RequireAuth role="gerante">
            <Suspense fallback={<RouteSuspenseFallback />}>
              <GerantePaiementsPage />
            </Suspense>
          </RequireAuth>
        }
      />
      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}

export default App
