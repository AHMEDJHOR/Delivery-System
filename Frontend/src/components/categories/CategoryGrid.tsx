import { Skeleton } from '@/components/ui/skeleton'
import type { FoodCategory } from '@/types/Categories'
import { CategoryCard } from './CategoryCard'

export interface CategoryGridProps {
  categories: FoodCategory[]
  isLoading?: boolean
  onEdit: (category: FoodCategory) => void
  onDelete: (category: FoodCategory) => void
}

export function CategoryGrid({ categories, isLoading = false, onEdit, onDelete }: CategoryGridProps) {
  if (isLoading) {
    return (
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        {Array.from({ length: 6 }).map((_, index) => (
          <Skeleton key={index} className="h-20 rounded-2xl" />
        ))}
      </div>
    )
  }

  if (categories.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-12 text-center text-sm text-gray-500">
        No food categories yet. Add a category to get started.
      </div>
    )
  }

  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      {categories.map((category) => (
        <CategoryCard
          key={category.id}
          category={category}
          onEdit={onEdit}
          onDelete={onDelete}
        />
      ))}
    </div>
  )
}
