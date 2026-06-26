import { useEffect, useRef, useState } from 'react'
import { ChevronDown } from 'lucide-react'
import { EcommerceLayout } from './components/EcommerceLayout'
import {
  Panel,
  Modal,
  FormField,
  ErrorState,
  StatusBadge,
  EmptyState,
  Pagination,
  inputClass,
  primaryButtonClass,
  secondaryButtonClass,
  money,
} from './components/EcommerceUi'
import { getCommandes, getCommandeById, updateCommandeStatus, markCommandeAsPaid } from './ecommerce.api'
import type { Commande, LaravelPaginated, CommandeStatut } from './ecommerce.types'

const statuts: CommandeStatut[] = [
  'en_attente',
  'confirmee',
  'en_preparation',
  'en_production',
  'prete',
  'en_livraison',
  'livree',
  'annulee',
  'echoue',
  'retournee',
]

const statutLabels: Record<CommandeStatut, string> = {
  en_attente: 'En attente',
  confirmee: 'Confirmée',
  en_preparation: 'En préparation',
  en_production: 'En production',
  prete: 'Prête',
  en_livraison: 'En livraison',
  livree: 'Livrée',
  annulee: 'Annulée',
  echoue: 'Échouée',
  retournee: 'Retournée',
}

export function CommandesPage() {
  const [items, setItems] = useState<LaravelPaginated<Commande> | null>(null)
  const [selectedCommande, setSelectedCommande] = useState<Commande | null>(null)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [statutFilter, setStatutFilter] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [detailOpen, setDetailOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [newStatut, setNewStatut] = useState<CommandeStatut>('confirmee')
  const filtersReady = useRef(false)

  useEffect(() => {
    loadCommandes()
  }, [])

  useEffect(() => {
    if (!filtersReady.current) {
      filtersReady.current = true
      return
    }
    const timer = setTimeout(() => {
      setPage(1)
      loadCommandes()
    }, 300)
    return () => clearTimeout(timer)
  }, [search, statutFilter])

  useEffect(() => {
    loadCommandes()
  }, [page])

  async function loadCommandes() {
    try {
      setLoading(true)
      setError(null)
      const data = await getCommandes(page, 15, search, statutFilter)
      setItems(data)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors du chargement des commandes')
    } finally {
      setLoading(false)
    }
  }

  async function handleOpenDetail(id: number) {
    try {
      const commande = await getCommandeById(id)
      setSelectedCommande(commande)
      setNewStatut(commande.statut)
      setDetailOpen(true)
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors du chargement')
    }
  }

  async function handleUpdateStatus() {
    if (!selectedCommande) return
    try {
      setSaving(true)
      setError(null)
      await updateCommandeStatus(selectedCommande.id, newStatut)
      setDetailOpen(false)
      loadCommandes()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors de la mise à jour')
    } finally {
      setSaving(false)
    }
  }

  async function handleMarkAsPaid() {
    if (!selectedCommande) return
    try {
      setSaving(true)
      setError(null)
      await markCommandeAsPaid(selectedCommande.id, {
        montant: selectedCommande.montant_total,
        methode: 'manual',
      })
      setDetailOpen(false)
      loadCommandes()
    } catch (err: any) {
      setError(err?.response?.data?.message || 'Erreur lors du paiement')
    } finally {
      setSaving(false)
    }
  }

  return (
    <EcommerceLayout>
      <Panel title="Gestion des commandes" subtitle="Suivi et gestion des commandes clients">
        {error && <ErrorState message={error} />}

        {/* Filtres */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 py-4">
          <input
            type="text"
            placeholder="Numéro ou client..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className={inputClass}
          />
          <select
            value={statutFilter}
            onChange={e => setStatutFilter(e.target.value)}
            className={inputClass}
          >
            <option value="">Tous les statuts</option>
            {statuts.map(s => (
              <option key={s} value={s}>
                {statutLabels[s]}
              </option>
            ))}
          </select>
          <div />
        </div>

        {/* Liste */}
        {loading ? (
          <div className="space-y-3">
            {[...Array(5)].map((_, i) => (
              <div key={i} className="h-16 bg-gray-200 rounded-lg animate-pulse" />
            ))}
          </div>
        ) : items?.data && items.data.length > 0 ? (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b border-gray-200 bg-gray-50">
                  <tr>
                    <th className="text-left px-4 py-3 font-semibold text-gray-700">Numéro</th>
                    <th className="text-left px-4 py-3 font-semibold text-gray-700">Client</th>
                    <th className="text-right px-4 py-3 font-semibold text-gray-700">Montant</th>
                    <th className="text-center px-4 py-3 font-semibold text-gray-700">Statut</th>
                    <th className="text-left px-4 py-3 font-semibold text-gray-700">Date</th>
                    <th className="text-left px-4 py-3 font-semibold text-gray-700">Source</th>
                    <th className="text-right px-4 py-3 font-semibold text-gray-700">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {items.data.map(commande => (
                    <tr key={commande.id} className="hover:bg-gray-50">
                      <td className="px-4 py-3 font-medium text-gray-900">{commande.numero_commande}</td>
                      <td className="px-4 py-3">
                        <div>
                          <div className="text-gray-900">{commande.nom_destinataire}</div>
                          <div className="text-xs text-gray-500">{commande.telephone_livraison}</div>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-right font-medium text-gray-900">{money(commande.montant_total)}</td>
                      <td className="px-4 py-3 text-center">
                        <StatusBadge status={commande.statut} label={statutLabels[commande.statut]} />
                      </td>
                      <td className="px-4 py-3 text-gray-700">
                        {new Date(commande.created_at || '').toLocaleDateString('fr-FR')}
                      </td>
                      <td className="px-4 py-3 text-gray-700 capitalize">{commande.source}</td>
                      <td className="px-4 py-3 text-right">
                        <button
                          onClick={() => handleOpenDetail(commande.id)}
                          className="px-3 py-2 text-sm bg-gray-100 text-gray-700 hover:bg-gray-200 rounded transition-colors"
                        >
                          Détails
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination
              currentPage={items.current_page}
              lastPage={items.last_page}
              onPageChange={setPage}
              total={items.total}
              perPage={items.per_page}
            />
          </>
        ) : (
          <EmptyState title="Aucune commande" description="Aucune commande ne correspond à votre recherche" />
        )}
      </Panel>

      {/* Modal Détails */}
      <Modal
        isOpen={detailOpen}
        title={`Commande ${selectedCommande?.numero_commande}`}
        onClose={() => setDetailOpen(false)}
      >
        {selectedCommande && (
          <div className="space-y-6 p-6">
            {/* Infos principales */}
            <div>
              <h3 className="font-semibold text-gray-900 mb-3">Informations générales</h3>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p className="text-gray-600">Numéro</p>
                  <p className="font-medium text-gray-900">{selectedCommande.numero_commande}</p>
                </div>
                <div>
                  <p className="text-gray-600">Date</p>
                  <p className="font-medium text-gray-900">
                    {new Date(selectedCommande.created_at || '').toLocaleDateString('fr-FR')}
                  </p>
                </div>
                <div>
                  <p className="text-gray-600">Montant</p>
                  <p className="font-medium text-gray-900">{money(selectedCommande.montant_total)}</p>
                </div>
                <div>
                  <p className="text-gray-600">Statut</p>
                  <StatusBadge status={selectedCommande.statut} label={statutLabels[selectedCommande.statut]} />
                </div>
              </div>
            </div>

            {/* Livraison */}
            <div className="border-t border-gray-200 pt-4">
              <h3 className="font-semibold text-gray-900 mb-3">Adresse de livraison</h3>
              <div className="text-sm text-gray-700 space-y-1">
                <p>{selectedCommande.nom_destinataire}</p>
                <p>{selectedCommande.adresse_livraison}</p>
                <p>{selectedCommande.telephone_livraison}</p>
              </div>
            </div>

            {/* Articles */}
            {selectedCommande.articles_commandes && selectedCommande.articles_commandes.length > 0 && (
              <div className="border-t border-gray-200 pt-4">
                <h3 className="font-semibold text-gray-900 mb-3">Articles</h3>
                <div className="space-y-2 text-sm">
                  {selectedCommande.articles_commandes.map(article => (
                    <div key={article.id} className="flex justify-between text-gray-700">
                      <span>
                        {article.nom_produit} ×{article.quantite}
                      </span>
                      <span className="font-medium">{money(article.prix_total_article)}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Actions */}
            <div className="border-t border-gray-200 pt-4 space-y-3">
              <FormField label="Changer le statut">
                <select
                  value={newStatut}
                  onChange={e => setNewStatut(e.target.value as CommandeStatut)}
                  className={inputClass}
                >
                  {statuts.map(s => (
                    <option key={s} value={s}>
                      {statutLabels[s]}
                    </option>
                  ))}
                </select>
              </FormField>

              <div className="flex gap-3">
                <button
                  onClick={handleUpdateStatus}
                  disabled={saving || newStatut === selectedCommande.statut}
                  className={`flex-1 ${primaryButtonClass}`}
                >
                  {saving ? 'Mise à jour...' : 'Mettre à jour le statut'}
                </button>
                <button
                  onClick={handleMarkAsPaid}
                  disabled={saving}
                  className={`flex-1 ${secondaryButtonClass}`}
                >
                  Marquer comme payée
                </button>
              </div>

              <button
                onClick={() => setDetailOpen(false)}
                className={`w-full ${secondaryButtonClass}`}
              >
                Fermer
              </button>
            </div>
          </div>
        )}
      </Modal>
    </EcommerceLayout>
  )
}
