import { useEffect, useState } from 'react';
import { apiClient } from '../../../lib/apiClient';
import EcommerceLayout from '../../../layouts/EcommerceLayout';
import type { Produit, LaravelPaginated } from './ecommerce.types';

export default function EcommerceTestPage() {
  const [produits, setProduits] = useState<Produit[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    async function fetchProduits() {
      try {
        const response = await apiClient.get('/api/admin/ecommerce/produits');
        const data = response.data as LaravelPaginated<Produit>;
        setProduits(data.data || []);
      } catch (err) {
        setError((err as Error).message);
      } finally {
        setLoading(false);
      }
    }
    fetchProduits();
  }, []);

  if (loading) return <EcommerceLayout><div className="p-6">Chargement...</div></EcommerceLayout>;
  if (error) return <EcommerceLayout><div className="p-6 text-red-600">Erreur: {error}</div></EcommerceLayout>;

  return (
    <EcommerceLayout>
      <div>
        <h1 className="text-2xl font-bold mb-6">Ecommerce — Produits ({produits.length})</h1>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {produits.map(p => (
            <div key={p.id} className="border rounded p-4">
              <h3 className="font-semibold">{p.nom}</h3>
              <p className="text-sm text-gray-600">{p.prix} FCFA</p>
              <p className="text-xs">Stock: {p.stock_disponible}</p>
              <span className={`text-xs px-2 py-1 rounded ${p.est_visible ? 'bg-green-100 text-green-700' : 'bg-gray-100'}`}>
                {p.est_visible ? 'Visible' : 'Masqué'}
              </span>
            </div>
          ))}
        </div>

        {produits.length === 0 && (
          <div className="text-center text-gray-500 mt-8">
            Aucun produit. Allez en créer un!
          </div>
        )}
      </div>
    </EcommerceLayout>
  );
}
