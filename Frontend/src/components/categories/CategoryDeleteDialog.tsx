import type { FoodCategory } from '@/types/Categories'
import { CategoryModal } from './CategoryModal'

export interface CategoryDeleteDialogProps {
  open: boolean
  category: FoodCategory | null
  isSubmitting?: boolean
  onClose: () => void
  onConfirm: () => void
}

export function CategoryDeleteDialog({
  open,
  category,
  isSubmitting = false,
  onClose,
  onConfirm,
}: CategoryDeleteDialogProps) {
  return (
    <CategoryModal
      open={open}
      title="Delete Category"
      subtitle={
        category
          ? `Are you sure you want to delete “${category.name}”? This cannot be undone.`
          : undefined
      }
      onClose={onClose}
      maxWidthClassName="max-w-md"
    >
      <div className="flex flex-wrap gap-3">
        <button
          type="button"
          disabled={isSubmitting || !category}
          onClick={onConfirm}
          className="rounded-full bg-red-500 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-red-600 disabled:opacity-50"
        >
          {isSubmitting ? 'Deleting...' : 'Delete'}
        </button>
        <button
          type="button"
          onClick={onClose}
          className="rounded-full border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-800 transition-colors hover:bg-gray-50"
        >
          Cancel
        </button>
      </div>
    </CategoryModal>
  )
}
