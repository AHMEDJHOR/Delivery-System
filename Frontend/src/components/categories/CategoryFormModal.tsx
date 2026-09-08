import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import type { CategoryFormInput, CategoryIconKey, FoodCategory } from '@/types/Categories'
import { CategoryModal } from './CategoryModal'
import { CATEGORY_ICON_OPTIONS } from './categoryIcons'

const schema = z.object({
  name: z.string().trim().min(1, 'Category name is required'),
  description: z.string().trim(),
  iconKey: z.enum([
    'bowl',
    'pizza',
    'burger',
    'sushi',
    'coffee',
    'dessert',
    'salad',
    'chicken',
    'default',
  ]),
})

export interface CategoryFormModalProps {
  open: boolean
  category?: FoodCategory | null
  isSubmitting?: boolean
  onClose: () => void
  onSubmit: (values: CategoryFormInput) => Promise<void> | void
}

export function CategoryFormModal({
  open,
  category,
  isSubmitting = false,
  onClose,
  onSubmit,
}: CategoryFormModalProps) {
  const isEditing = Boolean(category)
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CategoryFormInput>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: '',
      description: '',
      iconKey: 'default',
    },
  })

  useEffect(() => {
    if (!open) return
    reset({
      name: category?.name ?? '',
      description: category?.description ?? '',
      iconKey: category?.iconKey ?? 'default',
    })
  }, [open, category, reset])

  const closeAndReset = () => {
    reset()
    onClose()
  }

  return (
    <CategoryModal
      open={open}
      title={isEditing ? 'Edit Category' : 'Add Category'}
      subtitle={
        isEditing
          ? 'Update the category name, description, and icon.'
          : 'Create a food category restaurants can use.'
      }
      onClose={closeAndReset}
    >
      <form
        className="space-y-4"
        onSubmit={handleSubmit(async (values) => {
          await onSubmit(values)
        })}
      >
        <div className="space-y-2">
          <Label htmlFor="category-name" className="font-semibold text-gray-900">
            Category Name
          </Label>
          <Input
            id="category-name"
            placeholder="Category name"
            className="h-11 rounded-full border-gray-200"
            {...register('name')}
          />
          {errors.name && <p className="text-sm text-red-500">{errors.name.message}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="category-description" className="font-semibold text-gray-900">
            Description
          </Label>
          <Input
            id="category-description"
            placeholder="Optional description"
            className="h-11 rounded-full border-gray-200"
            {...register('description')}
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="category-icon" className="font-semibold text-gray-900">
            Icon
          </Label>
          <select
            id="category-icon"
            className="h-11 w-full rounded-full border border-gray-200 bg-white px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
            {...register('iconKey')}
          >
            {CATEGORY_ICON_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>

        <div className="flex flex-wrap gap-3 pt-2">
          <button
            type="submit"
            disabled={isSubmitting}
            className="rounded-full bg-orange-500 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-orange-600 disabled:opacity-50"
          >
            {isSubmitting ? 'Saving...' : isEditing ? 'Save Changes' : 'Create Category'}
          </button>
          <button
            type="button"
            onClick={closeAndReset}
            className="rounded-full border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-800 transition-colors hover:bg-gray-50"
          >
            Cancel
          </button>
        </div>
      </form>
    </CategoryModal>
  )
}

export type { CategoryIconKey }
