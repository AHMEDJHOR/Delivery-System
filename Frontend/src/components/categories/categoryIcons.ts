import type { LucideIcon } from 'lucide-react'
import {
  Soup,
  Pizza,
  Beef,
  Fish,
  Coffee,
  IceCream,
  Salad,
  Drumstick,
  UtensilsCrossed,
} from 'lucide-react'
import type { CategoryIconKey } from '@/types/Categories'

export interface CategoryIconOption {
  value: CategoryIconKey
  label: string
  icon: LucideIcon
}

export const CATEGORY_ICON_OPTIONS: CategoryIconOption[] = [
  { value: 'bowl', label: 'Bowl', icon: Soup },
  { value: 'pizza', label: 'Pizza', icon: Pizza },
  { value: 'burger', label: 'Burger', icon: Beef },
  { value: 'sushi', label: 'Sushi', icon: Fish },
  { value: 'coffee', label: 'Coffee', icon: Coffee },
  { value: 'dessert', label: 'Dessert', icon: IceCream },
  { value: 'salad', label: 'Salad', icon: Salad },
  { value: 'chicken', label: 'Chicken', icon: Drumstick },
  { value: 'default', label: 'General', icon: UtensilsCrossed },
]

const ICON_BY_KEY = Object.fromEntries(
  CATEGORY_ICON_OPTIONS.map((option) => [option.value, option.icon]),
) as Record<CategoryIconKey, LucideIcon>

export function getCategoryIcon(iconKey: CategoryIconKey, name = ''): LucideIcon {
  if (iconKey !== 'default') {
    return ICON_BY_KEY[iconKey]
  }

  const normalized = name.toLowerCase()
  if (normalized.includes('pizza')) return Pizza
  if (normalized.includes('burger')) return Beef
  if (normalized.includes('sushi') || normalized.includes('fish')) return Fish
  if (normalized.includes('coffee') || normalized.includes('drink')) return Coffee
  if (normalized.includes('dessert') || normalized.includes('ice')) return IceCream
  if (normalized.includes('salad') || normalized.includes('vegan')) return Salad
  if (normalized.includes('chicken')) return Drumstick
  if (normalized.includes('ethiopian') || normalized.includes('soup') || normalized.includes('stew')) {
    return Soup
  }

  return UtensilsCrossed
}

export function formatRestaurantCount(count: number): string {
  return count === 1 ? '1 restaurant' : `${count} restaurants`
}
