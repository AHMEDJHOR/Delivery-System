import { useCallback, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  createCategory,
  deleteCategory,
  fetchCategories,
  updateCategory,
} from '@/lib/categoriesApi'
import { fetchRestaurants } from '@/lib/restaurantsApi'
import type { CategoryFormInput, FoodCategory } from '@/types/Categories'

const QUERY_KEY = ['categories'] as const
const LOCAL_KEY = 'admin-local-categories'

function readLocal(): FoodCategory[] {
  try {
    const raw = sessionStorage.getItem(LOCAL_KEY)
    return raw ? (JSON.parse(raw) as FoodCategory[]) : []
  } catch {
    return []
  }
}

function writeLocal(categories: FoodCategory[]) {
  sessionStorage.setItem(LOCAL_KEY, JSON.stringify(categories))
}

function getErrorMessage(error: unknown): string {
  if (
    typeof error === 'object' &&
    error !== null &&
    'response' in error &&
    typeof error.response === 'object' &&
    error.response !== null &&
    'data' in error.response &&
    typeof error.response.data === 'object' &&
    error.response.data !== null &&
    'message' in error.response.data &&
    typeof error.response.data.message === 'string'
  ) {
    return error.response.data.message
  }

  if (error instanceof Error) return error.message
  return 'Something went wrong. Please try again.'
}

function mergeCategories(apiItems: FoodCategory[], localItems: FoodCategory[]): FoodCategory[] {
  const byId = new Map<number, FoodCategory>()
  const byName = new Map<string, FoodCategory>()

  for (const item of [...apiItems, ...localItems]) {
    const nameKey = item.name.toLowerCase()
    if (!byId.has(item.id) && !byName.has(nameKey)) {
      byId.set(item.id, item)
      byName.set(nameKey, item)
    }
  }

  return Array.from(byId.values()).sort((a, b) => a.name.localeCompare(b.name))
}

function withRestaurantCounts(
  categories: FoodCategory[],
  restaurantCategories: string[],
): FoodCategory[] {
  if (restaurantCategories.length === 0) return categories

  return categories.map((category) => {
    const count = restaurantCategories.filter(
      (name) => name.toLowerCase() === category.name.toLowerCase(),
    ).length

    return {
      ...category,
      restaurantCount: Math.max(category.restaurantCount, count),
    }
  })
}

export function useAdminCategories() {
  const queryClient = useQueryClient()
  const query = useQuery({
    queryKey: QUERY_KEY,
    queryFn: fetchCategories,
    staleTime: 30_000,
  })
  const restaurantsQuery = useQuery({
    queryKey: ['restaurants'],
    queryFn: fetchRestaurants,
    staleTime: 30_000,
  })
  const [localCategories, setLocalCategories] = useState<FoodCategory[]>(readLocal)
  const [isSaving, setIsSaving] = useState(false)

  const restaurantCategories = useMemo(
    () => (restaurantsQuery.data ?? []).map((restaurant) => restaurant.category).filter(Boolean),
    [restaurantsQuery.data],
  )

  const categories = useMemo(
    () => withRestaurantCounts(mergeCategories(query.data ?? [], localCategories), restaurantCategories),
    [query.data, localCategories, restaurantCategories],
  )

  const persistLocal = useCallback((next: FoodCategory[]) => {
    setLocalCategories(next)
    writeLocal(next)
  }, [])

  const nameExists = useCallback(
    (name: string, excludeId?: number) =>
      categories.some(
        (category) =>
          category.id !== excludeId && category.name.toLowerCase() === name.trim().toLowerCase(),
      ),
    [categories],
  )

  const addCategory = useCallback(
    async (input: CategoryFormInput) => {
      if (nameExists(input.name)) {
        toast.error('A category with this name already exists.')
        throw new Error('duplicate-name')
      }

      setIsSaving(true)
      try {
        const created = await createCategory(input)
        const category: FoodCategory = created ?? {
          id: Date.now(),
          name: input.name.trim(),
          description: input.description.trim(),
          iconKey: input.iconKey,
          restaurantCount: 0,
        }

        if (created) {
          queryClient.setQueryData<FoodCategory[]>(QUERY_KEY, (current) =>
            mergeCategories(current ?? [], [category]),
          )
        } else {
          persistLocal(mergeCategories([], [...localCategories, category]))
        }

        toast.success(`${category.name} was added.`)
      } catch (error) {
        if (error instanceof Error && error.message === 'duplicate-name') throw error
        toast.error(getErrorMessage(error))
        throw error
      } finally {
        setIsSaving(false)
      }
    },
    [localCategories, nameExists, persistLocal, queryClient],
  )

  const editCategory = useCallback(
    async (id: number, input: CategoryFormInput) => {
      if (nameExists(input.name, id)) {
        toast.error('A category with this name already exists.')
        throw new Error('duplicate-name')
      }

      setIsSaving(true)
      try {
        const updated = await updateCategory(id, input)
        const next: FoodCategory = updated ?? {
          id,
          name: input.name.trim(),
          description: input.description.trim(),
          iconKey: input.iconKey,
          restaurantCount: categories.find((item) => item.id === id)?.restaurantCount ?? 0,
        }

        queryClient.setQueryData<FoodCategory[]>(QUERY_KEY, (current) =>
          (current ?? []).map((item) => (item.id === id ? next : item)),
        )
        persistLocal(localCategories.map((item) => (item.id === id ? next : item)))
        toast.success(`${next.name} was updated.`)
      } catch (error) {
        if (error instanceof Error && error.message === 'duplicate-name') throw error
        toast.error(getErrorMessage(error))
        throw error
      } finally {
        setIsSaving(false)
      }
    },
    [categories, localCategories, nameExists, persistLocal, queryClient],
  )

  const removeCategory = useCallback(
    async (category: FoodCategory) => {
      setIsSaving(true)
      try {
        await deleteCategory(category.id)
        queryClient.setQueryData<FoodCategory[]>(QUERY_KEY, (current) =>
          (current ?? []).filter((item) => item.id !== category.id),
        )
        persistLocal(localCategories.filter((item) => item.id !== category.id))
        toast.success(`${category.name} was deleted.`)
      } catch (error) {
        toast.error(getErrorMessage(error))
        throw error
      } finally {
        setIsSaving(false)
      }
    },
    [localCategories, persistLocal, queryClient],
  )

  return {
    categories,
    isLoading: query.isLoading,
    isError: query.isError,
    errorMessage: query.error ? getErrorMessage(query.error) : null,
    isSaving,
    addCategory,
    editCategory,
    removeCategory,
  }
}
