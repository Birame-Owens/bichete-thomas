import { useState, useRef } from 'react'
import { X, AlertTriangle, ImageIcon, Plus, Trash2, Check } from 'lucide-react'
import { createProduit, updateProduit } from '../ecommerce.api'
import type { Produit, CategoryOption, TypeVariante } from '../ecommerce.types'
import { COLOR_PALETTE, LIGHT_HEXES } from '../../../../lib/colorPalette'

const ALL_VETEMENT_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '2A', '4A', '6A', '8A', '10A', '12A', '14A', '16A', 'Taille unique']
const ALL_CHAUSSURE_SIZES = ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', 'Pointure unique']

const VETEMENT_PRESETS: Record<string, string[]> = {
  'Prêt-à-porter': ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
  'Enfants': ['2A', '4A', '6A', '8A', '10A', '12A', '14A', '16A'],
  'Unique': ['Taille unique'],
}
const CHAUSSURE_PRESETS: Record<string, string[]> = {
  'Femme': ['35', '36', '37', '38', '39', '40', '41'],
  'Homme': ['39', '40', '41', '42', '43', '44', '45'],
  'Unique': ['Pointure unique'],
}

const ALL_CONTENANCES = ['5ml', '10ml', '15ml', '30ml', '50ml', '75ml', '100ml', '125ml', '150ml', '200ml', '250ml', '500ml', 'Contenance unique']

const CONTENANCE_PRESETS: Record<string, string[]> = {
  'Petites': ['15ml', '30ml', '50ml'],
  'Standards': ['75ml', '100ml', '125ml'],
  'Grandes': ['150ml', '200ml', '250ml', '500ml'],
  'Unique': ['Contenance unique'],
}

interface ColorVariant {
  colorName: string
  colorHex: string
  sizes: string[]
  stock: Record<string, number>
  seuil: Record<string, number>
  imageFiles: File[]
  imagePreviews: string[]
  existingImages: { id: number; url: string }[]
}

type Tab = 'infos' | 'variantes' | 'options' | 'seo'

interface Props {
  produit?: Produit | null
  categories: CategoryOption[]
  onClose: () => void
  onSuccess: (message: string) => void
}

const inputCls = 'w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#ff5ca5]/40 focus:border-[#ff5ca5] transition-all'

function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{children}</label>
}

function Toggle({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <label className="flex items-center justify-between px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-100 transition-colors">
      <span className="text-sm font-medium text-gray-900">{label}</span>
      <div
        onClick={() => onChange(!checked)}
        className={`w-10 h-6 rounded-full transition-colors relative flex-shrink-0 ${checked ? 'bg-[#e91e63]' : 'bg-gray-300'}`}
      >
        <span className={`absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${checked ? 'translate-x-5' : 'translate-x-1'}`} />
      </div>
    </label>
  )
}

export function ProductFormModal({ produit, categories, onClose, onSuccess }: Props) {
  const isEdit = !!produit
  const [tab, setTab] = useState<Tab>('infos')

  // Infos
  const [nom, setNom] = useState(produit?.nom ?? '')
  const [descCourte, setDescCourte] = useState(produit?.description_courte ?? '')
  const [desc, setDesc] = useState(produit?.description ?? '')
  const [categorieId, setCategorieId] = useState(produit?.categorie_id ? String(produit.categorie_id) : '')
  const [prix, setPrix] = useState(produit?.prix ? String(produit.prix) : '')
  const [prixPromo, setPrixPromo] = useState(produit?.prix_promo ? String(produit.prix_promo) : '')
  const [debutPromo, setDebutPromo] = useState(produit?.debut_promo?.slice(0, 10) ?? '')
  const [finPromo, setFinPromo] = useState(produit?.fin_promo?.slice(0, 10) ?? '')
  const [mainImageFile, setMainImageFile] = useState<File | null>(null)
  const [mainImagePreview, setMainImagePreview] = useState<string | null>(produit?.image_principale ?? null)
  const mainImageRef = useRef<HTMLInputElement>(null)

  // Type de variante
  const [typeVariante, setTypeVariante] = useState<TypeVariante>(produit?.type_variante ?? 'vetement')
  const [newSenteur, setNewSenteur] = useState('')
  const [stockGlobal, setStockGlobal] = useState(String(produit?.stock_disponible ?? 0))
  const [seuilGlobal, setSeuilGlobal] = useState(String(produit?.seuil_alerte ?? 5))

  // Variantes
  const [variants, setVariants] = useState<ColorVariant[]>(() => {
    if (!produit?.couleur_tailles) return []
    return Object.entries(produit.couleur_tailles).map(([colorName, sizes]) => {
      const pal = COLOR_PALETTE.find(c => c.name === colorName)
      const stockMap = produit.couleur_tailles_stock?.[colorName] ?? {}
      const seuilMap = produit.couleur_tailles_seuil?.[colorName] ?? {}
      const existingImgs = (produit.images ?? [])
        .filter(i => i.couleur_associee === colorName)
        .map(i => ({ id: i.id, url: i.url_miniature ?? i.url_originale }))
      return {
        colorName,
        colorHex: pal?.hex ?? '#9E9E9E',
        sizes,
        stock: stockMap,
        seuil: seuilMap,
        imageFiles: [],
        imagePreviews: [],
        existingImages: existingImgs,
      }
    })
  })
  const [customSize, setCustomSize] = useState<Record<string, string>>({})
  const colorImgRefs = useRef<Record<string, HTMLInputElement | null>>({})

  // Options
  const [estVisible, setEstVisible] = useState(produit?.est_visible ?? true)
  const [estPopulaire, setEstPopulaire] = useState(produit?.est_populaire ?? false)
  const [estNouveaute, setEstNouveaute] = useState(produit?.est_nouveaute ?? false)
  const [gestionStock, setGestionStock] = useState(produit?.gestion_stock ?? true)
  const [faitSurMesure, setFaitSurMesure] = useState(produit?.fait_sur_mesure ?? false)
  const [delai, setDelai] = useState(produit?.delai_production_jours ? String(produit.delai_production_jours) : '')
  const [cout, setCout] = useState(produit?.cout_production ? String(produit.cout_production) : '')
  const [materiaux, setMateriaux] = useState<string[]>(produit?.materiaux_necessaires ?? [])
  const [matInput, setMatInput] = useState('')
  const [ordre, setOrdre] = useState(String(produit?.ordre_affichage ?? 0))

  // SEO
  const [metaTitre, setMetaTitre] = useState(produit?.meta_titre ?? '')
  const [metaDesc, setMetaDesc] = useState(produit?.meta_description ?? '')
  const [tags, setTags] = useState(produit?.tags ?? '')

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const parents = categories.filter(c => !c.parent_id)
  const childrenOf = (pid: number) => categories.filter(c => c.parent_id === pid)

  // Labels dynamiques selon le type
  const isParfum = typeVariante === 'parfum'
  const isChaussure = typeVariante === 'chaussure'
  const axis2Label = isParfum ? 'Contenances' : isChaussure ? 'Pointures' : 'Tailles'
  const allSizes = isParfum ? ALL_CONTENANCES : isChaussure ? ALL_CHAUSSURE_SIZES : ALL_VETEMENT_SIZES
  const sizePresets = isParfum ? CONTENANCE_PRESETS : isChaussure ? CHAUSSURE_PRESETS : VETEMENT_PRESETS

  const addSenteur = () => {
    const name = newSenteur.trim()
    if (!name || variants.some(v => v.colorName === name)) return
    setVariants(prev => [...prev, { colorName: name, colorHex: '#C19A6B', sizes: [], stock: {}, seuil: {}, imageFiles: [], imagePreviews: [], existingImages: [] }])
    setNewSenteur('')
  }

  const addColor = (name: string, hex: string) => {
    if (variants.some(v => v.colorName === name)) return
    setVariants(prev => [...prev, { colorName: name, colorHex: hex, sizes: [], stock: {}, seuil: {}, imageFiles: [], imagePreviews: [], existingImages: [] }])
  }

  const removeColor = (name: string) => setVariants(prev => prev.filter(v => v.colorName !== name))

  const addSize = (color: string, size: string) =>
    setVariants(prev => prev.map(v =>
      v.colorName === color && !v.sizes.includes(size) ? { ...v, sizes: [...v.sizes, size] } : v
    ))

  const removeSize = (color: string, size: string) =>
    setVariants(prev => prev.map(v =>
      v.colorName === color
        ? {
            ...v,
            sizes: v.sizes.filter(s => s !== size),
            stock: Object.fromEntries(Object.entries(v.stock).filter(([k]) => k !== size)),
            seuil: Object.fromEntries(Object.entries(v.seuil).filter(([k]) => k !== size)),
          }
        : v
    ))

  const updateStock = (color: string, size: string, val: string) =>
    setVariants(prev => prev.map(v =>
      v.colorName === color ? { ...v, stock: { ...v.stock, [size]: Number(val) || 0 } } : v
    ))

  const updateSeuil = (color: string, size: string, val: string) =>
    setVariants(prev => prev.map(v =>
      v.colorName === color ? { ...v, seuil: { ...v.seuil, [size]: Number(val) || 0 } } : v
    ))

  const addMatiere = () => {
    const v = matInput.trim()
    if (v && !materiaux.includes(v)) setMateriaux(prev => [...prev, v])
    setMatInput('')
  }

  const addColorImages = (colorName: string, files: FileList) => {
    const newFiles = Array.from(files)
    const newPreviews = newFiles.map(f => URL.createObjectURL(f))
    setVariants(prev => prev.map(v =>
      v.colorName === colorName
        ? { ...v, imageFiles: [...v.imageFiles, ...newFiles], imagePreviews: [...v.imagePreviews, ...newPreviews] }
        : v
    ))
  }

  const removeColorImage = (colorName: string, idx: number) => {
    setVariants(prev => prev.map(v => {
      if (v.colorName !== colorName) return v
      return {
        ...v,
        imageFiles: v.imageFiles.filter((_, i) => i !== idx),
        imagePreviews: v.imagePreviews.filter((_, i) => i !== idx),
      }
    }))
  }

  const removeExistingImage = (colorName: string, imageId: number) => {
    setVariants(prev => prev.map(v =>
      v.colorName === colorName
        ? { ...v, existingImages: v.existingImages.filter(img => img.id !== imageId) }
        : v
    ))
  }

  const buildFd = (): FormData => {
    const fd = new FormData()
    fd.append('nom', nom.trim())
    if (descCourte) fd.append('description_courte', descCourte)
    fd.append('description', desc)
    fd.append('categorie_id', categorieId)
    fd.append('prix', prix || '0')
    if (prixPromo) fd.append('prix_promo', prixPromo)
    if (debutPromo) fd.append('debut_promo', debutPromo)
    if (finPromo) fd.append('fin_promo', finPromo)
    fd.append('type_variante', typeVariante)
    if (typeVariante === 'aucun') {
      fd.append('stock_disponible', stockGlobal || '0')
      fd.append('seuil_alerte', seuilGlobal || '0')
    }
    fd.append('est_visible', estVisible ? '1' : '0')
    fd.append('est_populaire', estPopulaire ? '1' : '0')
    fd.append('est_nouveaute', estNouveaute ? '1' : '0')
    fd.append('gestion_stock', gestionStock ? '1' : '0')
    fd.append('fait_sur_mesure', faitSurMesure ? '1' : '0')
    if (delai) fd.append('delai_production_jours', delai)
    if (cout) fd.append('cout_production', cout)
    materiaux.forEach(m => fd.append('materiaux_necessaires[]', m))
    fd.append('ordre_affichage', ordre || '0')
    if (metaTitre) fd.append('meta_titre', metaTitre)
    if (metaDesc) fd.append('meta_description', metaDesc)
    if (tags) fd.append('tags', tags)
    if (mainImageFile) fd.append('image_principale', mainImageFile)

    // Variantes : tailles + stock
    variants.forEach(v => {
      if (v.sizes.length > 0) {
        v.sizes.forEach((size, idx) => {
          fd.append(`couleur_tailles[${v.colorName}][${idx}]`, size)
          fd.append(`couleur_tailles_stock[${v.colorName}][${size}]`, String(v.stock[size] ?? 0))
          if (v.seuil[size] != null) fd.append(`couleur_tailles_seuil[${v.colorName}][${size}]`, String(v.seuil[size]))
        })
      } else {
        // Couleur sans taille : stock global de cette couleur sous la clé '_'
        fd.append(`couleur_tailles_stock[${v.colorName}][_]`, String(v.stock['_'] ?? 0))
        if (v.seuil['_'] != null) fd.append(`couleur_tailles_seuil[${v.colorName}][_]`, String(v.seuil['_']))
      }
    })

    // Photos couleur : images[] + image_couleurs[idx] = nom couleur
    let colorImgIdx = 0
    variants.forEach(v => {
      v.imageFiles.forEach(file => {
        fd.append('images[]', file)
        fd.append(`image_couleurs[${colorImgIdx}]`, v.colorName)
        colorImgIdx++
      })
    })

    // Images existantes à supprimer (non conservées)
    if (isEdit) {
      const allOriginalIds = (produit?.images ?? [])
        .filter(i => i.couleur_associee !== null)
        .map(i => i.id)
      const keptIds = variants.flatMap(v => v.existingImages.map(img => img.id))
      allOriginalIds
        .filter(id => !keptIds.includes(id))
        .forEach(id => fd.append('images_to_delete[]', String(id)))
    }

    return fd
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    if (!nom.trim()) { setError('Le nom est obligatoire.'); setTab('infos'); return }
    if (!desc.trim() || desc.trim().length < 10) { setError('La description est obligatoire (minimum 10 caractères).'); setTab('infos'); return }
    if (!categorieId) { setError('La catégorie est obligatoire.'); setTab('infos'); return }
    if (!prix || isNaN(Number(prix))) { setError('Le prix est obligatoire.'); setTab('infos'); return }
    if (!isEdit && !mainImageFile) { setError("L'image principale est obligatoire."); setTab('infos'); return }
    setSubmitting(true)
    try {
      const fd = buildFd()
      if (isEdit && produit) {
        await updateProduit(produit.id, fd)
        onSuccess('Produit modifié avec succès !')
      } else {
        await createProduit(fd)
        onSuccess('Produit créé avec succès !')
      }
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data
      setError(msg?.errors ? Object.values(msg.errors).flat().join(' ') : (msg?.message ?? 'Une erreur est survenue.'))
    } finally {
      setSubmitting(false)
    }
  }

  const varianteTabLabel = isParfum ? 'Senteurs & Contenances' : typeVariante === 'aucun' ? 'Stock' : isChaussure ? 'Couleurs & Pointures' : 'Couleurs & Tailles'
  const TABS: { key: Tab; label: string }[] = [
    { key: 'infos', label: 'Infos' },
    { key: 'variantes', label: varianteTabLabel },
    { key: 'options', label: 'Options' },
    { key: 'seo', label: 'SEO' },
  ]

  return (
    <div
      className="fixed inset-0 bg-black/30 backdrop-blur-[2px] z-50 flex items-center justify-center p-4"
      onClick={e => { if (e.target === e.currentTarget) onClose() }}
    >
      <div className="bg-white rounded-3xl border border-gray-200 shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col">

        {/* Header */}
        <div className="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-200 flex-shrink-0">
          <div>
            <h2 className="font-bold text-gray-900 text-lg">
              {isEdit ? `Modifier — ${produit!.nom}` : 'Nouveau produit'}
            </h2>
            <p className="text-xs text-gray-500 mt-0.5">
              {isEdit ? 'Modifiez les informations du produit.' : 'Renseignez les informations du nouveau produit.'}
            </p>
          </div>
          <button onClick={onClose} className="p-2 rounded-xl hover:bg-gray-100 transition-colors">
            <X className="w-4 h-4 text-gray-500" strokeWidth={1.5} />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex gap-1 px-6 pt-4 flex-shrink-0 overflow-x-auto">
          {TABS.map(t => (
            <button
              key={t.key}
              type="button"
              onClick={() => setTab(t.key)}
              className={`px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors ${
                tab === t.key ? 'bg-[#e91e63] text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100'
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>

        {/* Scrollable body */}
        <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto">
          <div className="p-6 space-y-4">

            {/* ── Infos ── */}
            {tab === 'infos' && (
              <>
                {/* Image principale */}
                <div>
                  <Label>Image principale</Label>
                  <div
                    onClick={() => mainImageRef.current?.click()}
                    className="relative w-full h-40 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center cursor-pointer hover:bg-gray-100 transition-colors overflow-hidden"
                  >
                    {mainImagePreview
                      ? <img src={mainImagePreview} alt="" className="w-full h-full object-cover" />
                      : <div className="flex flex-col items-center gap-2">
                          <ImageIcon className="w-6 h-6 text-gray-400" strokeWidth={1.5} />
                          <span className="text-xs text-gray-500">Cliquer pour uploader</span>
                        </div>
                    }
                    {mainImagePreview && (
                      <button
                        type="button"
                        onClick={e => { e.stopPropagation(); setMainImageFile(null); setMainImagePreview(null) }}
                        className="absolute top-2 right-2 p-1 bg-black/40 text-white rounded-full hover:bg-black/60"
                      >
                        <X className="w-3 h-3" />
                      </button>
                    )}
                  </div>
                  <input
                    ref={mainImageRef} type="file" accept="image/*" className="hidden"
                    onChange={e => {
                      const f = e.target.files?.[0]
                      if (f) { setMainImageFile(f); setMainImagePreview(URL.createObjectURL(f)) }
                    }}
                  />
                </div>

                <div>
                  <Label>Nom <span className="text-rose-400">*</span></Label>
                  <input type="text" value={nom} onChange={e => setNom(e.target.value)}
                    placeholder="Ex: Huile capillaire nourrissante" className={inputCls} />
                </div>

                <div>
                  <Label>Description courte</Label>
                  <textarea value={descCourte ?? ''} onChange={e => setDescCourte(e.target.value)}
                    placeholder="Une phrase de présentation…" rows={2} className={`${inputCls} resize-none`} />
                </div>

                <div>
                  <Label>Description complète</Label>
                  <textarea value={desc} onChange={e => setDesc(e.target.value)}
                    placeholder="Description détaillée du produit…" rows={4} className={`${inputCls} resize-none`} />
                </div>

                <div>
                  <Label>Catégorie <span className="text-rose-400">*</span></Label>
                  <select value={categorieId} onChange={e => setCategorieId(e.target.value)} className={inputCls}>
                    <option value="">Sélectionner une catégorie…</option>
                    {parents.map(p => (
                      <optgroup key={p.id} label={p.nom}>
                        <option value={p.id}>{p.nom}</option>
                        {childrenOf(p.id).map(c => (
                          <option key={c.id} value={c.id}>&nbsp;&nbsp;└ {c.nom}</option>
                        ))}
                      </optgroup>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <Label>Prix (FCFA) <span className="text-rose-400">*</span></Label>
                    <input type="number" min="0" value={prix} onChange={e => setPrix(e.target.value)}
                      placeholder="25000" className={inputCls} />
                  </div>
                  <div>
                    <Label>Prix promo (FCFA)</Label>
                    <input type="number" min="0" value={prixPromo} onChange={e => setPrixPromo(e.target.value)}
                      placeholder="20000" className={inputCls} />
                  </div>
                </div>

                {prixPromo && (
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <Label>Début promo</Label>
                      <input type="date" value={debutPromo} onChange={e => setDebutPromo(e.target.value)} className={inputCls} />
                    </div>
                    <div>
                      <Label>Fin promo</Label>
                      <input type="date" value={finPromo} onChange={e => setFinPromo(e.target.value)} className={inputCls} />
                    </div>
                  </div>
                )}
              </>
            )}

            {/* ── Variantes ── */}
            {tab === 'variantes' && (
              <>
                {/* Sélecteur de type de variante */}
                <div>
                  <Label>Type de produit</Label>
                  <div className="grid grid-cols-2 gap-2">
                    {([
                      { value: 'vetement', label: 'Vêtement / Mode', icon: '👗', desc: 'Couleurs & tailles' },
                      { value: 'chaussure', label: 'Chaussures', icon: '👟', desc: 'Couleurs & pointures' },
                      { value: 'parfum', label: 'Parfum / Huile', icon: '🌸', desc: 'Senteurs & contenances' },
                      { value: 'aucun', label: 'Sans variante', icon: '📦', desc: 'Stock global uniquement' },
                    ] as { value: TypeVariante; label: string; icon: string; desc: string }[]).map(opt => (
                      <button
                        key={opt.value}
                        type="button"
                        onClick={() => { if (opt.value !== typeVariante) { setTypeVariante(opt.value); setVariants([]) } }}
                        className={`flex flex-col items-center gap-1 p-3 rounded-xl border-2 transition-all text-center ${
                          typeVariante === opt.value
                            ? 'border-[#e91e63] bg-pink-50 shadow-sm'
                            : 'border-gray-200 bg-white hover:bg-gray-50'
                        }`}
                      >
                        <span className="text-xl">{opt.icon}</span>
                        <span className="text-[11px] font-bold text-gray-900 leading-tight">{opt.label}</span>
                        <span className="text-[10px] text-gray-500">{opt.desc}</span>
                      </button>
                    ))}
                  </div>
                </div>

                {/* ── Type: aucun → stock global ── */}
                {typeVariante === 'aucun' && (
                  <div className="bg-gray-50 rounded-2xl border border-gray-200 p-4 space-y-3">
                    <p className="text-xs text-gray-500">Ce produit n'a pas de variantes. Définissez le stock global ci-dessous.</p>
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Stock disponible</p>
                        <input type="number" min="0" value={stockGlobal} onChange={e => setStockGlobal(e.target.value)}
                          placeholder="0" className={inputCls} />
                      </div>
                      <div>
                        <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Seuil d'alerte</p>
                        <input type="number" min="0" value={seuilGlobal} onChange={e => setSeuilGlobal(e.target.value)}
                          placeholder="5" className={inputCls} />
                      </div>
                    </div>
                  </div>
                )}

                {/* ── Type: parfum → ajout senteurs ── */}
                {isParfum && (
                  <div>
                    <Label>Senteurs disponibles</Label>
                    <div className="p-4 bg-gray-50 rounded-2xl border border-gray-200 space-y-3">
                      <div className="flex gap-2">
                        <input
                          type="text"
                          value={newSenteur}
                          onChange={e => setNewSenteur(e.target.value)}
                          onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); addSenteur() } }}
                          placeholder="Ex: Rose, Jasmin, Oud, Musc…"
                          className={`${inputCls} flex-1`}
                        />
                        <button type="button" onClick={addSenteur}
                          className="px-4 py-3 bg-[#e91e63] text-white rounded-xl text-sm font-semibold hover:bg-[#d81b60] transition-colors flex-shrink-0">
                          <Plus className="w-4 h-4" strokeWidth={2.5} />
                        </button>
                      </div>
                      {variants.length > 0 ? (
                        <div className="flex flex-wrap gap-2">
                          {variants.map(v => (
                            <span key={v.colorName}
                              className="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 rounded-full text-xs font-semibold text-gray-900 shadow-sm">
                              🌸 {v.colorName}
                              <button type="button" onClick={() => removeColor(v.colorName)}>
                                <X className="w-3 h-3 text-gray-500 hover:text-rose-400" strokeWidth={2} />
                              </button>
                            </span>
                          ))}
                        </div>
                      ) : (
                        <p className="text-xs text-gray-400 text-center py-1">Ajoutez les senteurs disponibles pour ce produit.</p>
                      )}
                    </div>
                  </div>
                )}

                {/* ── Type: vetement / chaussure → palette couleurs ── */}
                {(typeVariante === 'vetement' || typeVariante === 'chaussure') && (
                  <div>
                    <Label>Palette de couleurs</Label>
                    <div className="flex flex-wrap gap-2 p-4 bg-gray-50 rounded-2xl border border-gray-200">
                      {COLOR_PALETTE.map(color => {
                        const selected = variants.some(v => v.colorName === color.name)
                        return (
                          <button
                            key={color.name}
                            type="button"
                            title={color.name}
                            onClick={() => selected ? removeColor(color.name) : addColor(color.name, color.hex)}
                            className={`w-8 h-8 rounded-full border-2 flex items-center justify-center transition-transform hover:scale-110 ${
                              selected ? 'border-[#e91e63] scale-110' : 'border-transparent hover:border-gray-300'
                            }`}
                            style={{ backgroundColor: color.hex }}
                          >
                            {selected && (
                              <Check
                                className="w-3.5 h-3.5 drop-shadow"
                                strokeWidth={3}
                                style={{ color: LIGHT_HEXES.includes(color.hex) ? '#1A1A1A' : '#ffffff' }}
                              />
                            )}
                          </button>
                        )
                      })}
                    </div>
                  </div>
                )}

                {/* Variantes construites */}
                {typeVariante !== 'aucun' && variants.length === 0 ? (
                  <div className="bg-gray-50 rounded-2xl border border-dashed border-gray-300 p-8 text-center">
                    <p className="text-sm text-gray-500">
                      {isParfum ? 'Ajoutez des senteurs ci-dessus pour créer des variantes.' : 'Sélectionnez des couleurs dans la palette pour créer des variantes.'}
                    </p>
                  </div>
                ) : typeVariante !== 'aucun' && (
                  <div className="space-y-4">
                    {variants.map(variant => (
                      <div key={variant.colorName} className="border border-gray-200 rounded-2xl p-4 space-y-3 bg-white">
                        {/* Header */}
                        <div className="flex items-center gap-3">
                          {isParfum ? (
                            <span className="text-lg flex-shrink-0">🌸</span>
                          ) : (
                            <span className="w-5 h-5 rounded-full border border-gray-200 flex-shrink-0" style={{ backgroundColor: variant.colorHex }} />
                          )}
                          <span className="font-semibold text-sm text-gray-900 flex-1">{variant.colorName}</span>
                          <button type="button" onClick={() => removeColor(variant.colorName)}
                            className="p-1.5 rounded-lg hover:bg-rose-50 transition-colors">
                            <Trash2 className="w-3.5 h-3.5 text-rose-400" strokeWidth={1.5} />
                          </button>
                        </div>

                        {/* Presets */}
                        <div>
                          <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-2">
                            {isParfum ? 'Ajouter des contenances' : 'Ajouter par groupe'}
                          </p>
                          <div className="flex flex-wrap gap-2 mb-3">
                            {Object.entries(sizePresets).map(([presetName, sizes]) => (
                              <button
                                key={presetName}
                                type="button"
                                onClick={() => sizes.forEach(s => addSize(variant.colorName, s))}
                                className="px-2.5 py-1 rounded-lg bg-gray-100 text-[11px] font-semibold text-gray-500 hover:bg-gray-200 transition-colors"
                              >
                                {presetName}
                              </button>
                            ))}
                          </div>

                          {/* Individual sizes / contenances */}
                          <div className="flex flex-wrap gap-1.5">
                            {allSizes.map(size => {
                              const active = variant.sizes.includes(size)
                              return (
                                <button
                                  key={size}
                                  type="button"
                                  onClick={() => active ? removeSize(variant.colorName, size) : addSize(variant.colorName, size)}
                                  className={`px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-colors ${
                                    active ? 'bg-[#e91e63] text-white' : 'bg-gray-50 border border-gray-200 text-gray-500 hover:bg-gray-100'
                                  }`}
                                >
                                  {size}
                                </button>
                              )
                            })}
                            <input
                              type="text"
                              value={customSize[variant.colorName] ?? ''}
                              onChange={e => setCustomSize(prev => ({ ...prev, [variant.colorName]: e.target.value }))}
                              onKeyDown={e => {
                                if (e.key !== 'Enter') return
                                e.preventDefault()
                                const val = customSize[variant.colorName]?.trim()
                                if (val) { addSize(variant.colorName, val); setCustomSize(prev => ({ ...prev, [variant.colorName]: '' })) }
                              }}
                              placeholder={isParfum ? 'Ex: 75ml…' : isChaussure ? 'Ex: 46…' : 'Autre…'}
                              className="w-24 px-2 py-1 rounded-lg bg-gray-50 border border-gray-200 text-[11px] text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-[#ff5ca5]"
                            />
                          </div>
                        </div>

                        {/* Stock grid */}
                        {variant.sizes.length > 0 ? (
                          <div>
                            <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-2">
                              Stock & Seuil alerte par {axis2Label.toLowerCase().replace(/s$/, '')}
                            </p>
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                              {variant.sizes.map(size => (
                                <div key={size} className="bg-gray-50 rounded-xl p-2.5">
                                  <p className="text-[11px] font-bold text-gray-900 mb-1.5">{size}</p>
                                  <input
                                    type="number" min="0"
                                    value={variant.stock[size] ?? ''}
                                    onChange={e => updateStock(variant.colorName, size, e.target.value)}
                                    placeholder="Stock"
                                    className="w-full px-2 py-1 mb-1 bg-white border border-gray-200 rounded-lg text-xs text-gray-900 focus:outline-none focus:border-[#ff5ca5]"
                                  />
                                  <input
                                    type="number" min="0"
                                    value={variant.seuil[size] ?? ''}
                                    onChange={e => updateSeuil(variant.colorName, size, e.target.value)}
                                    placeholder="Seuil alerte"
                                    className="w-full px-2 py-1 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 focus:outline-none focus:border-[#ff5ca5]"
                                  />
                                </div>
                              ))}
                            </div>
                          </div>
                        ) : (
                          <div>
                            <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-2">Stock & Seuil alerte</p>
                            <div className="grid grid-cols-2 gap-2">
                              <div className="bg-gray-50 rounded-xl p-2.5">
                                <p className="text-[11px] font-bold text-gray-900 mb-1.5">Quantité en stock</p>
                                <input
                                  type="number" min="0"
                                  value={variant.stock['_'] ?? ''}
                                  onChange={e => updateStock(variant.colorName, '_', e.target.value)}
                                  placeholder="Stock"
                                  className="w-full px-2 py-1 bg-white border border-gray-200 rounded-lg text-xs text-gray-900 focus:outline-none focus:border-[#ff5ca5]"
                                />
                              </div>
                              <div className="bg-gray-50 rounded-xl p-2.5">
                                <p className="text-[11px] font-bold text-gray-900 mb-1.5">Seuil alerte</p>
                                <input
                                  type="number" min="0"
                                  value={variant.seuil['_'] ?? ''}
                                  onChange={e => updateSeuil(variant.colorName, '_', e.target.value)}
                                  placeholder="Seuil"
                                  className="w-full px-2 py-1 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 focus:outline-none focus:border-[#ff5ca5]"
                                />
                              </div>
                            </div>
                          </div>
                        )}

                        {/* Images par senteur/couleur */}
                        <div>
                          <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-2">
                            Photos pour {isParfum ? 'cette senteur' : 'cette couleur'}
                            <span className="ml-1 font-normal normal-case text-gray-400">
                              ({variant.existingImages.length + variant.imagePreviews.length} photo{variant.existingImages.length + variant.imagePreviews.length !== 1 ? 's' : ''})
                            </span>
                          </p>
                          <div className="flex flex-wrap gap-2">
                            {/* Images existantes cote serveur */}
                            {variant.existingImages.map(img => (
                              <div key={img.id} className="relative w-20 h-20 rounded-xl overflow-hidden border border-gray-200">
                                <img src={img.url} alt="" className="w-full h-full object-cover" />
                                <button
                                  type="button"
                                  onClick={() => removeExistingImage(variant.colorName, img.id)}
                                  className="absolute top-1 right-1 p-0.5 bg-black/50 text-white rounded-full hover:bg-black/70"
                                >
                                  <X className="w-2.5 h-2.5" />
                                </button>
                              </div>
                            ))}

                            {/* Nouvelles images */}
                            {variant.imagePreviews.map((preview, idx) => (
                              <div key={idx} className="relative w-20 h-20 rounded-xl overflow-hidden border border-gray-300">
                                <img src={preview} alt="" className="w-full h-full object-cover" />
                                <button
                                  type="button"
                                  onClick={() => removeColorImage(variant.colorName, idx)}
                                  className="absolute top-1 right-1 p-0.5 bg-black/50 text-white rounded-full hover:bg-black/70"
                                >
                                  <X className="w-2.5 h-2.5" />
                                </button>
                              </div>
                            ))}

                            {/* Bouton ajouter */}
                            <button
                              type="button"
                              onClick={() => colorImgRefs.current[variant.colorName]?.click()}
                              className="w-20 h-20 rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 flex flex-col items-center justify-center gap-1 hover:bg-gray-100 transition-colors"
                            >
                              <Plus className="w-4 h-4 text-gray-400" strokeWidth={2} />
                              <span className="text-[10px] text-gray-500">Ajouter</span>
                            </button>
                          </div>
                          <input
                            ref={el => { colorImgRefs.current[variant.colorName] = el }}
                            type="file" accept="image/*" multiple className="hidden"
                            onChange={e => { if (e.target.files?.length) addColorImages(variant.colorName, e.target.files) }}
                          />
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </>
            )}

            {/* ── Options ── */}
            {tab === 'options' && (
              <>
                <div className="grid grid-cols-2 gap-3">
                  <Toggle checked={estVisible} onChange={setEstVisible} label="Visible" />
                  <Toggle checked={estPopulaire} onChange={setEstPopulaire} label="Populaire" />
                  <Toggle checked={estNouveaute} onChange={setEstNouveaute} label="Nouveauté" />
                  <Toggle checked={gestionStock} onChange={setGestionStock} label="Gestion stock" />
                  <Toggle checked={faitSurMesure} onChange={setFaitSurMesure} label="Fait sur mesure" />
                </div>

                {faitSurMesure && (
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <Label>Délai production (jours)</Label>
                      <input type="number" min="0" value={delai} onChange={e => setDelai(e.target.value)}
                        placeholder="7" className={inputCls} />
                    </div>
                    <div>
                      <Label>Coût production (FCFA)</Label>
                      <input type="number" min="0" value={cout} onChange={e => setCout(e.target.value)}
                        placeholder="5000" className={inputCls} />
                    </div>
                  </div>
                )}

                <div>
                  <Label>Matières nécessaires</Label>
                  <div className="flex gap-2 mb-2">
                    <input
                      type="text" value={matInput}
                      onChange={e => setMatInput(e.target.value)}
                      onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); addMatiere() } }}
                      placeholder="Ex: Karité, Huile de coco…"
                      className={`${inputCls} flex-1`}
                    />
                    <button type="button" onClick={addMatiere}
                      className="px-4 py-3 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                      <Plus className="w-4 h-4 text-gray-500" strokeWidth={2.5} />
                    </button>
                  </div>
                  {materiaux.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                      {materiaux.map(m => (
                        <span key={m} className="flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 rounded-full text-xs font-medium text-gray-900">
                          {m}
                          <button type="button" onClick={() => setMateriaux(prev => prev.filter(x => x !== m))}>
                            <X className="w-3 h-3 text-gray-500 hover:text-rose-400" strokeWidth={2} />
                          </button>
                        </span>
                      ))}
                    </div>
                  )}
                </div>

                <div>
                  <Label>Ordre d'affichage</Label>
                  <input type="number" min="0" value={ordre} onChange={e => setOrdre(e.target.value)} className={inputCls} />
                </div>
              </>
            )}

            {/* ── SEO ── */}
            {tab === 'seo' && (
              <>
                <div>
                  <Label>Meta titre</Label>
                  <input type="text" value={metaTitre ?? ''} onChange={e => setMetaTitre(e.target.value)}
                    placeholder="Titre pour les moteurs de recherche…" className={inputCls} />
                  <p className="text-[11px] text-gray-500 mt-1">{(metaTitre ?? '').length}/70 caractères</p>
                </div>
                <div>
                  <Label>Meta description</Label>
                  <textarea value={metaDesc ?? ''} onChange={e => setMetaDesc(e.target.value)}
                    placeholder="Description pour les moteurs de recherche…" rows={3}
                    className={`${inputCls} resize-none`} />
                  <p className="text-[11px] text-gray-500 mt-1">{(metaDesc ?? '').length}/160 caractères</p>
                </div>
                <div>
                  <Label>Tags</Label>
                  <input type="text" value={tags} onChange={e => setTags(e.target.value)}
                    placeholder="huile, soin, cheveux, naturel…" className={inputCls} />
                  <p className="text-[11px] text-gray-500 mt-1">Séparés par des virgules. Laissez vide pour un remplissage automatique.</p>
                </div>
              </>
            )}

            {error && (
              <div className="flex items-center gap-2.5 px-4 py-3 bg-rose-50 border border-rose-200 rounded-xl">
                <AlertTriangle className="w-4 h-4 text-rose-500 flex-shrink-0" strokeWidth={1.5} />
                <p className="text-sm text-rose-600">{error}</p>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="flex gap-3 px-6 pb-6">
            <button type="button" onClick={onClose}
              className="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-500 hover:bg-gray-100 transition-colors">
              Annuler
            </button>
            <button type="submit" disabled={submitting}
              className="flex-1 py-3 rounded-xl bg-[#e91e63] text-white text-sm font-semibold hover:bg-[#d81b60] transition-colors disabled:opacity-50 shadow-sm">
              {submitting ? 'Enregistrement…' : isEdit ? 'Enregistrer' : 'Créer le produit'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
