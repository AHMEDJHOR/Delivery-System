import { Pencil, Trash2 } from 'lucide-react'
import type { FoodCategory } from '@/types/Categories'
import { formatRestaurantCount, getCategoryIcon } from './categoryIcons'

export interface CategoryCardProps {
  category: FoodCategory
  onEdit: (category: FoodCategory) => void
  onDelete: (category: FoodCategory) => void
}

export function CategoryCard({ category, onEdit, onDelete }: CategoryCardProps) {
  const Icon = getCategoryIcon(category.iconKey, category.name)

  return (
    <article className="flex items-center gap-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
      <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-500">
        <Icon className="h-6 w-6" />
      </div>

      <div className="min-w-0 flex-1">
        <h3 className="truncate font-semibold text-gray-900">{category.name}</h3>
        <p className="text-sm text-gray-500">{formatRestaurantCount(category.restaurantCount)}</p>
      </div>

      <div className="flex shrink-0 items-center gap-2">
        <button
          type="button"
          onClick={() => onEdit(category)}
          className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition-colors hover:bg-gray-50"
          aria-label={`Edit ${category.name}`}
        >
          <Pencil className="h-4 w-4" />
        </button>
        <button
          type="button"
          onClick={() => onDelete(category)}
          className="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-500 transition-colors hover:bg-red-100"
          aria-label={`Delete ${category.name}`}
        >
          <Trash2 className="h-4 w-4" />
        </button>
      </div>
    </article>
  )
}
