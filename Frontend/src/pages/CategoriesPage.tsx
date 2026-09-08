import { useState } from 'react'
import { Plus } from 'lucide-react'
import type { FoodCategory } from '@/types/Categories'
import { useAdminCategories } from '@/hooks/useAdminCategories'
import {
  CategoryDeleteDialog,
  CategoryFormModal,
  CategoryGrid,
} from '@/components/categories'

export default function CategoriesPage() {
  const {
    categories,
    isLoading,
    isError,
    errorMessage,
    isSaving,
    addCategory,
    editCategory,
    removeCategory,
  } = useAdminCategories()
  const [formOpen, setFormOpen] = useState(false)
  const [editingCategory, setEditingCategory] = useState<FoodCategory | null>(null)
  const [deletingCategory, setDeletingCategory] = useState<FoodCategory | null>(null)

  const closeForm = () => {
    setFormOpen(false)
    setEditingCategory(null)
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Food Categories</h1>
        <button
          type="button"
          onClick={() => {
            setEditingCategory(null)
            setFormOpen(true)
          }}
          className="inline-flex items-center gap-1 rounded-full bg-orange-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-orange-600"
        >
          <Plus className="h-4 w-4" />
          Add Category
        </button>
      </div>

      {isError && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {errorMessage ?? 'Failed to load categories. Please try again.'}
        </div>
      )}

      <CategoryGrid
        categories={categories}
        isLoading={isLoading}
        onEdit={(category) => {
          setEditingCategory(category)
          setFormOpen(true)
        }}
        onDelete={setDeletingCategory}
      />

      <CategoryFormModal
        open={formOpen}
        category={editingCategory}
        isSubmitting={isSaving}
        onClose={closeForm}
        onSubmit={async (values) => {
          if (editingCategory) {
            await editCategory(editingCategory.id, values)
          } else {
            await addCategory(values)
          }
          closeForm()
        }}
      />

      <CategoryDeleteDialog
        open={Boolean(deletingCategory)}
        category={deletingCategory}
        isSubmitting={isSaving}
        onClose={() => setDeletingCategory(null)}
        onConfirm={async () => {
          if (!deletingCategory) return
          await removeCategory(deletingCategory)
          setDeletingCategory(null)
        }}
      />
    </div>
  )
}
