import axios from 'axios'
import api from '@/lib/api'
import type { CategoryFormInput, CategoryIconKey, FoodCategory } from '@/types/Categories'

interface CategoryApiRecord {
  id: number
  name: string
  description?: string | null
  icon?: string
  icon_key?: string
  iconKey?: string
  restaurants_count?: number
  restaurantCount?: number
  restaurants?: unknown[]
}

const ICON_KEYS: CategoryIconKey[] = [
  'bowl',
  'pizza',
  'burger',
  'sushi',
  'coffee',
  'dessert',
  'salad',
  'chicken',
  'default',
]

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

function unwrapList(payload: unknown): CategoryApiRecord[] {
  if (Array.isArray(payload)) return payload as CategoryApiRecord[]
  if (isRecord(payload) && Array.isArray(payload.data)) return payload.data as CategoryApiRecord[]
  if (isRecord(payload) && isRecord(payload.data) && Array.isArray(payload.data.data)) {
    return payload.data.data as CategoryApiRecord[]
  }
  return []
}

function unwrapRecord(payload: unknown): CategoryApiRecord | null {
  if (isRecord(payload) && isRecord(payload.data) && 'id' in payload.data) {
    return payload.data as unknown as CategoryApiRecord
  }
  if (isRecord(payload) && 'id' in payload) {
    return payload as unknown as CategoryApiRecord
  }
  return null
}

function normalizeIconKey(value: string | undefined): CategoryIconKey {
  if (value && ICON_KEYS.includes(value as CategoryIconKey)) {
    return value as CategoryIconKey
  }
  return 'default'
}

export function mapCategory(record: CategoryApiRecord): FoodCategory {
  return {
    id: record.id,
    name: record.name,
    description: record.description ?? '',
    iconKey: normalizeIconKey(record.iconKey ?? record.icon_key ?? record.icon),
    restaurantCount:
      record.restaurantCount ??
      record.restaurants_count ??
      (Array.isArray(record.restaurants) ? record.restaurants.length : 0),
  }
}

function isMissingEndpoint(error: unknown): boolean {
  return axios.isAxiosError(error) && (error.response?.status === 404 || error.response?.status === 405)
}

export async function fetchCategories(): Promise<FoodCategory[]> {
  try {
    const response = await api.get('/categories')
    return unwrapList(response.data).map(mapCategory)
  } catch (error) {
    if (isMissingEndpoint(error)) return []
    throw error
  }
}

export async function createCategory(input: CategoryFormInput): Promise<FoodCategory | null> {
  try {
    const response = await api.post('/categories', {
      name: input.name,
      description: input.description,
      icon: input.iconKey,
    })
    const record = unwrapRecord(response.data)
    return record ? mapCategory(record) : null
  } catch (error) {
    if (isMissingEndpoint(error)) return null
    throw error
  }
}

export async function updateCategory(
  id: number,
  input: CategoryFormInput,
): Promise<FoodCategory | null> {
  try {
    const response = await api.put(`/categories/${id}`, {
      name: input.name,
      description: input.description,
      icon: input.iconKey,
    })
    const record = unwrapRecord(response.data)
    return record ? mapCategory(record) : null
  } catch (error) {
    if (isMissingEndpoint(error)) return null
    throw error
  }
}

export async function deleteCategory(id: number): Promise<boolean> {
  try {
    await api.delete(`/categories/${id}`)
    return true
  } catch (error) {
    if (isMissingEndpoint(error)) return false
    throw error
  }
}
