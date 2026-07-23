// Palette de couleurs proposees pour les variantes produits (boutique).
// Le nom est stocke en base (couleur_tailles / couleur_associee des images),
// le hex ne sert qu'a l'affichage admin.

export interface PaletteColor {
  name: string
  hex: string
}

export const COLOR_PALETTE: PaletteColor[] = [
  { name: 'Blanc', hex: '#FAFAFA' },
  { name: 'Ivoire', hex: '#F5F0E8' },
  { name: 'Beige', hex: '#D4B896' },
  { name: 'Camel', hex: '#C19A6B' },
  { name: 'Marron', hex: '#795548' },
  { name: 'Chocolat', hex: '#4E342E' },
  { name: 'Noir', hex: '#1A1A1A' },
  { name: 'Gris clair', hex: '#D4D4D4' },
  { name: 'Gris', hex: '#9E9E9E' },
  { name: 'Rouge', hex: '#D32F2F' },
  { name: 'Bordeaux', hex: '#7B1F2B' },
  { name: 'Rose', hex: '#FFCCD5' },
  { name: 'Rose fuchsia', hex: '#E91E63' },
  { name: 'Corail', hex: '#FFAB91' },
  { name: 'Orange', hex: '#F57C00' },
  { name: 'Jaune', hex: '#FDD835' },
  { name: 'Vert', hex: '#388E3C' },
  { name: 'Vert olive', hex: '#6B8E23' },
  { name: 'Turquoise', hex: '#00897B' },
  { name: 'Bleu ciel', hex: '#4FC3F7' },
  { name: 'Bleu royal', hex: '#1565C0' },
  { name: 'Bleu marine', hex: '#0D2149' },
  { name: 'Violet', hex: '#7B1FA2' },
  { name: 'Lavande', hex: '#CE93D8' },
  { name: 'Doré', hex: '#D4AF37' },
  { name: 'Argenté', hex: '#C0C0C0' },
]

/** Hex clairs sur lesquels une coche blanche serait invisible. */
export const LIGHT_HEXES = [
  '#FAFAFA', '#F5F0E8', '#D4B896', '#C19A6B', '#D4D4D4', '#9E9E9E',
  '#FFCCD5', '#FFAB91', '#FDD835', '#CE93D8', '#D4AF37', '#C0C0C0', '#4FC3F7',
]
