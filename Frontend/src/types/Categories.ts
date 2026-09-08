export type CategoryIconKey =
  | 'bowl'
  | 'pizza'
  | 'burger'
  | 'sushi'
  | 'coffee'
  | 'dessert'
  | 'salad'
  | 'chicken'
  | 'default'

export interface FoodCategory {
  id: number
  name: string
  description: string
  iconKey: CategoryIconKey
  restaurantCount: number
}

export interface CategoryFormInput {
  name: string
  description: string
  iconKey: CategoryIconKey
}
