# Food & Grocery Delivery Platform

## Database Design Document

**Project:** Food & Grocery Delivery Platform (PWA)
**Team:** Team 3 – 2026 Summer Internship

---

## 1. Introduction

This document describes the database design for the Food & Grocery Delivery Platform. It serves as the foundation for backend development and provides a shared understanding of the database structure before implementation.

The design supports the following user roles:

- Customer
- Restaurant Manager
- Driver
- Administrator

The goal is to define the database tables, relationships, constraints, and business rules that will be implemented using Laravel migrations and MySQL.

---

## 2. Database Design Goals

The database is designed to:

- Support all four user roles using a single `users` table.
- Maintain data integrity through primary and foreign keys.
- Prevent duplicate and inconsistent data.
- Preserve historical order information.
- Support role-based access control.
- Support online payments.
- Support real-time driver location tracking with location history.
- Allow future system expansion without major redesign.
- Provide a reliable foundation for the Laravel REST API.

---

## 3. Database Tables

The application consists of the following tables:

1. `users`
2. `driver_profiles`
3. `restaurants`
4. `categories`
5. `menu_items`
6. `cart_items`
7. `orders`
8. `order_items`
9. `ratings`
10. `payments`
11. `driver_locations`

Laravel may also generate system tables such as:

- `personal_access_tokens`
- `password_reset_tokens`
- `failed_jobs`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `migrations`

---

## 3.1 users

### Purpose

Stores all registered users of the platform. Customers, Restaurant Managers, Drivers, and Administrators are represented in this table. The `role` column determines the user's role and permissions.

| Column Name         | Data Type       | Constraints                  | Description                                             |
| ------------------- | --------------- | ---------------------------- | ------------------------------------------------------- |
| `id`                | BIGINT UNSIGNED | Primary Key, Auto Increment  | Unique user identifier.                                 |
| `name`              | VARCHAR(255)    | NOT NULL                     | Full name of the user.                                  |
| `email`             | VARCHAR(255)    | NOT NULL, UNIQUE             | Email address used for authentication.                  |
| `username`          | VARCHAR(255)    | NOT NULL, UNIQUE             | Username used by the authentication system.             |
| `phone`             | VARCHAR(20)     | NOT NULL, UNIQUE             | User's primary contact number.                          |
| `password`          | VARCHAR(255)    | NOT NULL                     | Hashed password.                                        |
| `role`              | ENUM            | NOT NULL, DEFAULT `customer` | `customer`, `restaurant_manager`, `driver`, or `admin`. |
| `status`            | ENUM            | NOT NULL, DEFAULT `active`   | `active`, `inactive`, or `suspended`.                   |
| `email_verified_at` | TIMESTAMP       | NULL                         | Date and time when the email was verified.              |
| `created_at`        | TIMESTAMP       | Auto Generated               | Record creation timestamp.                              |
| `updated_at`        | TIMESTAMP       | Auto Generated               | Record update timestamp.                                |

### Indexes

| Column     | Index Type  | Purpose                            |
| ---------- | ----------- | ---------------------------------- |
| `id`       | Primary Key | Unique record identification.      |
| `email`    | UNIQUE      | Prevent duplicate email addresses. |
| `username` | UNIQUE      | Prevent duplicate usernames.       |
| `phone`    | UNIQUE      | Prevent duplicate phone numbers.   |
| `role`     | INDEX       | Improve role-based queries.        |
| `status`   | INDEX       | Improve account-status filtering.  |

### Relationships

- One Customer can place many orders.
- One Customer can have many cart items.
- One Customer can submit many ratings.
- One Restaurant Manager can manage many restaurants.
- One Driver has one driver profile.

---

## 3.2 driver_profiles

### Purpose

Stores information specific to drivers. Each driver account has one driver profile containing delivery-related information.

| Column Name       | Data Type       | Constraints                       | Description                                                   |
| ----------------- | --------------- | --------------------------------- | ------------------------------------------------------------- | --- |
| `id`              | BIGINT UNSIGNED | Primary Key, Auto Increment       | Unique driver profile identifier.                             |
| `user_id`         | BIGINT UNSIGNED | NOT NULL, UNIQUE, FK → `users.id` | Associated driver account.                                    |
| `vehicle_type`    | VARCHAR(100)    | NOT NULL                          | Type of vehicle used for deliveries.                          |
| `license_number`  | VARCHAR(100)    | NULL, UNIQUE                      | Driver's license number.                                      |
| `approval_status` | ENUM            | NOT NULL, DEFAULT `pending`       | Driver approval status: `pending`, `approved`, or `rejected`. |     |
| `is_online`       | BOOLEAN         | NOT NULL, DEFAULT FALSE           | Indicates whether the driver is available.                    |
| `created_at`      | TIMESTAMP       | Auto Generated                    | Record creation timestamp.                                    |
| `updated_at`      | TIMESTAMP       | Auto Generated                    | Record update timestamp.                                      |

### Indexes

| Column            | Index Type | Purpose                                       |
| ----------------- | ---------- | --------------------------------------------- |
| `approval_status` | INDEX      | Improve filtering drivers by approval status. |

### Foreign Keys

| Column    | References  | On Delete | On Update |
| --------- | ----------- | --------- | --------- |
| `user_id` | `users(id)` | CASCADE   | CASCADE   |

### Relationships

- One User with the Driver role has one driver profile.
- One Driver Profile belongs to one User.
- One Driver Profile can be assigned to many orders.
- One Driver Profile can have many location records.
- A driver must be approved before being allowed to receive delivery assignments.

---

## 3.3 restaurants

### Purpose

Stores restaurants and grocery stores registered on the platform. Each restaurant is managed by one Restaurant Manager.

| Column Name       | Data Type       | Constraints                  | Description                             |
| ----------------- | --------------- | ---------------------------- | --------------------------------------- |
| `id`              | BIGINT UNSIGNED | Primary Key, Auto Increment  | Unique restaurant identifier.           |
| `manager_id`      | BIGINT UNSIGNED | NOT NULL, FK → `users.id`    | Manager responsible for the restaurant. |
| `name`            | VARCHAR(255)    | NOT NULL                     | Restaurant or store name.               |
| `description`     | TEXT            | NULL                         | Optional description.                   |
| `address`         | TEXT            | NOT NULL                     | Restaurant address.                     |
| `phone`           | VARCHAR(20)     | NOT NULL                     | Restaurant contact number.              |
| `logo`            | VARCHAR(255)    | NULL                         | Restaurant logo path.                   |
| `latitude` | DECIMAL(10,7) | NULL | Geographic latitude of the restaurant, used for delivery-distance calculation. |
| `longitude` | DECIMAL(10,7) | NULL | Geographic longitude of the restaurant, used for delivery-distance calculation. |
| `approval_status` | ENUM            | NOT NULL, DEFAULT `pending`  | `pending`, `approved`, or `rejected`.   |
| `status`          | ENUM            | NOT NULL, DEFAULT `inactive` | `active`, `inactive`, or `suspended`.   |
| `created_at`      | TIMESTAMP       | Auto Generated               | Record creation timestamp.              |
| `updated_at`      | TIMESTAMP       | Auto Generated               | Record update timestamp.                |

### Foreign Keys

| Column       | References  | On Delete | On Update |
| ------------ | ----------- | --------- | --------- |
| `manager_id` | `users(id)` | RESTRICT  | CASCADE   |

### Relationships

- One Restaurant belongs to one Restaurant Manager.
- One Restaurant Manager can manage many restaurants.
- One Restaurant has many menu items.
- One Restaurant receives many orders.

---

## 3.4 categories

### Purpose

Stores categories used to organize menu items.

| Column Name   | Data Type       | Constraints                 | Description                    |
| ------------- | --------------- | --------------------------- | ------------------------------ |
| `id`          | BIGINT UNSIGNED | Primary Key, Auto Increment | Unique category identifier.    |
| `name`        | VARCHAR(100)    | NOT NULL, UNIQUE            | Category name.                 |
| `description` | TEXT            | NULL                        | Optional category description. |
| `created_at`  | TIMESTAMP       | Auto Generated              | Record creation timestamp.     |
| `updated_at`  | TIMESTAMP       | Auto Generated              | Record update timestamp.       |

### Relationships

- One Category contains many menu items.
- One Menu Item belongs to one Category.

---

## 3.5 menu_items

### Purpose

Stores food and grocery items offered by restaurants.

| Column Name     | Data Type       | Constraints                     | Description                                       |
| --------------- | --------------- | ------------------------------- | ------------------------------------------------- |
| `id`            | BIGINT UNSIGNED | Primary Key, Auto Increment     | Unique menu item identifier.                      |
| `restaurant_id` | BIGINT UNSIGNED | NOT NULL, FK → `restaurants.id` | Restaurant offering the item.                     |
| `category_id`   | BIGINT UNSIGNED | NOT NULL, FK → `categories.id`  | Category of the item.                             |
| `name`          | VARCHAR(255)    | NOT NULL                        | Menu item name.                                   |
| `description`   | TEXT            | NULL                            | Optional description.                             |
| `price`         | DECIMAL(10,2)   | NOT NULL, CHECK > 0             | Current selling price. Must be greater than zero. |
| `is_available`  | BOOLEAN         | NOT NULL, DEFAULT TRUE          | Whether the item is available.                    |
| `image`         | VARCHAR(255)    | NULL                            | Menu item image path.                             |
| `created_at`    | TIMESTAMP       | Auto Generated                  | Record creation timestamp.                        |
| `updated_at`    | TIMESTAMP       | Auto Generated                  | Record update timestamp.                          |

### Indexes

| Column | Index Type | Purpose                          |
| ------ | ---------- | -------------------------------- |
| `name` | INDEX      | Improve menu item name searches. |

### Foreign Keys

| Column          | References        | On Delete | On Update |
| --------------- | ----------------- | --------- | --------- |
| `restaurant_id` | `restaurants(id)` | CASCADE   | CASCADE   |
| `category_id`   | `categories(id)`  | RESTRICT  | CASCADE   |

### Relationships

- One Restaurant has many menu items.
- One Category contains many menu items.
- One Menu Item can appear in many cart items.
- One Menu Item can appear in many order items.
- One Menu Item can have many ratings.

---

## 3.6 cart_items

### Purpose

Stores the items currently selected by customers before checkout.

| Column Name    | Data Type       | Constraints                    | Description                      |
| -------------- | --------------- | ------------------------------ | -------------------------------- |
| `id`           | BIGINT UNSIGNED | Primary Key, Auto Increment    | Unique cart item identifier.     |
| `customer_id`  | BIGINT UNSIGNED | NOT NULL, FK → `users.id`      | Customer who owns the cart item. |
| `menu_item_id` | BIGINT UNSIGNED | NOT NULL, FK → `menu_items.id` | Selected menu item.              |
| `quantity`     | INT UNSIGNED    | NOT NULL, DEFAULT 1            | Quantity selected.               |
| `created_at`   | TIMESTAMP       | Auto Generated                 | Record creation timestamp.       |
| `updated_at`   | TIMESTAMP       | Auto Generated                 | Record update timestamp.         |

### Indexes

| Column                          | Index Type | Purpose                                                       |
| ------------------------------- | ---------- | ------------------------------------------------------------- |
| (`customer_id`, `menu_item_id`) | UNIQUE     | Ensure a customer has only one cart entry for each menu item. |

### Foreign Keys

| Column         | References       | On Delete | On Update |
| -------------- | ---------------- | --------- | --------- |
| `customer_id`  | `users(id)`      | CASCADE   | CASCADE   |
| `menu_item_id` | `menu_items(id)` | CASCADE   | CASCADE   |

### Relationships

- One Customer can have many cart items.
- One Menu Item can appear in many cart items.

---

## 3.7 orders

### Purpose

Stores customer orders from placement through delivery. Each order is associated with a customer, restaurant, and optionally an assigned driver.

| Column Name        | Data Type       | Constraints                     | Description                                                                                        |
| ------------------ | --------------- | ------------------------------- | -------------------------------------------------------------------------------------------------- |
| `id`               | BIGINT UNSIGNED | Primary Key, Auto Increment     | Unique order identifier.                                                                           |
| `customer_id`      | BIGINT UNSIGNED | NOT NULL, FK → `users.id`       | Customer who placed the order.                                                                     |
| `restaurant_id`    | BIGINT UNSIGNED | NOT NULL, FK → `restaurants.id` | Restaurant receiving the order.                                                                    |
| `driver_id`        | BIGINT UNSIGNED | NULL, FK → `driver_profiles.id` | Driver assigned to deliver the order.                                                              |
| `subtotal`         | DECIMAL(10,2)   | NOT NULL                        | Cost of ordered items before delivery fee.                                                         |
| `delivery_fee`     | DECIMAL(10,2)   | NOT NULL, DEFAULT 0.00          | Delivery charge.                                                                                   |
| `delivery_status` | ENUM | NOT NULL, DEFAULT `pending` | Status of delivery-fee calculation: `pending`, `calculated`, or `failed`. |
| `total_amount` | DECIMAL(10,2) | NOT NULL | Order total. Initially equals the subtotal while delivery-fee calculation is pending, then is updated after the delivery fee is calculated. |
| `delivery_address` | TEXT            | NOT NULL                        | Customer's delivery address.                                                                       |
| `delivery_latitude` | DECIMAL(10,7) | NULL | Geographic latitude of the customer's delivery location. |
| `delivery_longitude` | DECIMAL(10,7) | NULL | Geographic longitude of the customer's delivery location. |
| `phone`            | VARCHAR(20)     | NOT NULL                        | Contact phone used for the order.                                                                  |
| `status`           | ENUM            | NOT NULL, DEFAULT `pending`     | `pending`, `preparing`, `ready_for_pickup`, `in_transit`, `delivered`, `cancelled`, or `rejected`. |
| `assigned_at`      | TIMESTAMP       | NULL                            | Time when a driver was assigned.                                                                   |
| `delivered_at`     | TIMESTAMP       | NULL                            | Time when the order was delivered.                                                                 |
| `created_at`       | TIMESTAMP       | Auto Generated                  | Record creation timestamp.                                                                         |
| `updated_at`       | TIMESTAMP       | Auto Generated                  | Record update timestamp.                                                                           |

### Foreign Keys

| Column          | References            | On Delete | On Update |
| --------------- | --------------------- | --------- | --------- |
| `customer_id`   | `users(id)`           | RESTRICT  | CASCADE   |
| `restaurant_id` | `restaurants(id)`     | RESTRICT  | CASCADE   |
| `driver_id`     | `driver_profiles(id)` | SET NULL  | CASCADE   |

### Relationships

- One Customer places many orders.
- One Restaurant receives many orders.
- One Driver Profile can be assigned to many orders.
- One Order contains many order items.
- One Order can have zero or one payment.

### Delivery Distance and Fee Calculation

The backend calculates the delivery fee using the restaurant's coordinates and the customer's delivery coordinates.

Delivery-fee calculation is performed asynchronously after the order is created. The order is initially created with:

- `delivery_status`: `pending`
- `delivery_fee`: `0.00`
- `total_amount`: equal to the order subtotal

A queued `CalculateDeliveryFee` job then calculates the road distance through the routing service and updates the order.

Current pricing configuration:

- Service fee: `20.00` ETB
- Distance rate: `20.00` ETB per kilometer
- Maximum delivery fee: `1020.00` ETB
- Routing service: OSRM
- Routing request timeout: `5` seconds

The delivery fee is calculated as:

```text
delivery_fee = service_fee + (distance_km × distance_rate_per_km)
```

---

## 3.8 order_items

### Purpose

Stores each individual item included in an order. Item information is preserved at checkout so that historical orders remain accurate even if the menu item changes later.

| Column Name    | Data Type       | Constraints                 | Description                                  |
| -------------- | --------------- | --------------------------- | -------------------------------------------- |
| `id`           | BIGINT UNSIGNED | Primary Key, Auto Increment | Unique order item identifier.                |
| `order_id`     | BIGINT UNSIGNED | NOT NULL, FK → `orders.id`  | Order containing the item.                   |
| `menu_item_id` | BIGINT UNSIGNED | NULL, FK → `menu_items.id`  | Original menu item reference.                |
| `item_name`    | VARCHAR(255)    | NOT NULL                    | Item name at purchase time.                  |
| `quantity`     | INT UNSIGNED    | NOT NULL, CHECK > 0         | Quantity ordered. Must be greater than zero. |
| `unit_price`   | DECIMAL(10,2)   | NOT NULL                    | Price per unit at purchase time.             |
| `subtotal`     | DECIMAL(10,2)   | NOT NULL                    | Quantity × unit price.                       |
| `created_at`   | TIMESTAMP       | Auto Generated              | Record creation timestamp.                   |
| `updated_at`   | TIMESTAMP       | Auto Generated              | Record update timestamp.                     |

### Foreign Keys

| Column         | References       | On Delete | On Update |
| -------------- | ---------------- | --------- | --------- |
| `order_id`     | `orders(id)`     | CASCADE   | CASCADE   |
| `menu_item_id` | `menu_items(id)` | SET NULL  | CASCADE   |

### Relationships

- One Order contains many order items.
- One Menu Item can appear in many order items.
- One Order Item can have zero or one rating.

---

## 3.9 ratings

### Purpose

Stores customer ratings and optional reviews for menu items purchased through the platform.

The `order_item_id` relationship connects the rating to a specific purchased item. This allows the system to verify that the customer actually purchased the item before submitting a rating.

| Column Name     | Data Type        | Constraints                             | Description                                |
| --------------- | ---------------- | --------------------------------------- | ------------------------------------------ |
| `id`            | BIGINT UNSIGNED  | Primary Key, Auto Increment             | Unique rating identifier.                  |
| `customer_id`   | BIGINT UNSIGNED  | NOT NULL, FK → `users.id`               | Customer submitting the rating.            |
| `menu_item_id`  | BIGINT UNSIGNED  | NOT NULL, FK → `menu_items.id`          | Menu item being rated.                     |
| `order_item_id` | BIGINT UNSIGNED  | NOT NULL, UNIQUE, FK → `order_items.id` | Purchased item associated with the rating. |
| `rating`        | TINYINT UNSIGNED | NOT NULL, CHECK 1–5                     | Rating value from 1 to 5.                  |
| `comment`       | TEXT             | NULL                                    | Optional customer review.                  |
| `created_at`    | TIMESTAMP        | Auto Generated                          | Record creation timestamp.                 |
| `updated_at`    | TIMESTAMP        | Auto Generated                          | Record update timestamp.                   |

### Foreign Keys

| Column          | References        | On Delete | On Update |
| --------------- | ----------------- | --------- | --------- |
| `customer_id`   | `users(id)`       | RESTRICT  | CASCADE   |
| `menu_item_id`  | `menu_items(id)`  | RESTRICT  | CASCADE   |
| `order_item_id` | `order_items(id)` | CASCADE   | CASCADE   |

### Relationships

- One Customer can submit many ratings.
- One Menu Item can have many ratings.
- One Order Item can have at most one rating.
- One Rating belongs to one Customer.
- One Rating belongs to one Menu Item.
- One Rating belongs to one Order Item.

### Business Rules

- Only authenticated customers can submit ratings.
- A customer can rate a menu item only after purchasing it.
- A customer can submit at most one rating for each purchased order item.
- The rating value must be between 1 and 5.
- The comment is optional.
- Ratings should normally be submitted after the related order has been delivered.
- The system can calculate a menu item's average rating from its related ratings.

---

## 3.10 payments

### Purpose

Stores online payment information associated with customer orders. Payments are processed through supported online payment methods rather than being collected by the driver.

| Column Name             | Data Type       | Constraints                        | Description                                       |
| ----------------------- | --------------- | ---------------------------------- | ------------------------------------------------- |
| `id`                    | BIGINT UNSIGNED | Primary Key, Auto Increment        | Unique payment identifier.                        |
| `order_id`              | BIGINT UNSIGNED | NOT NULL, UNIQUE, FK → `orders.id` | Order associated with the payment.                |
| `amount`                | DECIMAL(10,2)   | NOT NULL                           | Amount processed for the order.                   |
| `payment_method`        | ENUM            | NOT NULL                           | Allowed values: `telebirr` or `card`.             |
| `status`                | ENUM            | NOT NULL, DEFAULT `pending`        | `pending`, `paid`, `failed`, or `refunded`.       |
| `transaction_reference` | VARCHAR(255)    | NULL, UNIQUE                       | Payment gateway transaction/reference identifier. |
| `paid_at`               | TIMESTAMP       | NULL                               | Date and time payment was successfully completed. |
| `created_at`            | TIMESTAMP       | Auto Generated                     | Record creation timestamp.                        |
| `updated_at`            | TIMESTAMP       | Auto Generated                     | Record update timestamp.                          |

### Foreign Keys

| Column     | References   | On Delete | On Update |
| ---------- | ------------ | --------- | --------- |
| `order_id` | `orders(id)` | CASCADE   | CASCADE   |

### Relationships

- One Order can have zero or one payment.
- One Payment belongs to one Order.

### Business Rules

- Payment is associated with an order.
- The payment amount should match the amount required by the order.
- A payment starts with `pending` status.
- A successful transaction changes the payment status to `paid`.
- Failed transactions are recorded with `failed` status.
- A refunded payment is recorded with `refunded` status.
- Drivers do not collect cash from customers.

---

## 3.11 driver_locations

### Purpose

Stores the location history of drivers while they are working. Every location update creates a new record rather than overwriting the previous location.

This allows the system to retrieve the driver's latest location for real-time tracking while also preserving historical location data.

| Column Name         | Data Type       | Constraints                         | Description                          |
| ------------------- | --------------- | ----------------------------------- | ------------------------------------ |
| `id`                | BIGINT UNSIGNED | Primary Key, Auto Increment         | Unique location record identifier.   |
| `driver_profile_id` | BIGINT UNSIGNED | NOT NULL, FK → `driver_profiles.id` | Driver associated with the location. |
| `latitude`          | DECIMAL(10,7)   | NOT NULL                            | Geographic latitude.                 |
| `longitude`         | DECIMAL(10,7)   | NOT NULL                            | Geographic longitude.                |
| `recorded_at`       | TIMESTAMP       | NOT NULL                            | Time when the location was recorded. |
| `created_at`        | TIMESTAMP       | Auto Generated                      | Record creation timestamp.           |
| `updated_at`        | TIMESTAMP       | Auto Generated                      | Record update timestamp.             |

### Indexes

| Column                               | Index Type  | Purpose                                                                  |
| ------------------------------------ | ----------- | ------------------------------------------------------------------------ |
| `id`                                 | Primary Key | Unique record identification.                                            |
| (`driver_profile_id`, `recorded_at`) | INDEX       | Improve retrieval of a driver's location history in chronological order. |

### Foreign Keys

| Column              | References            | On Delete | On Update |
| ------------------- | --------------------- | --------- | --------- |
| `driver_profile_id` | `driver_profiles(id)` | CASCADE   | CASCADE   |

### Relationships

- One Driver Profile can have many location records.
- One Driver Location belongs to one Driver Profile.

### Business Rules

- Each location update creates a new record.
- The latest location record represents the driver's current known location.
- Historical location records are retained for tracking and delivery analysis.
- Location data should only be recorded while the driver is active or available for tracking.
- Latitude and longitude must contain valid geographic coordinates.

---

## 4. Main Entity Relationships

The main relationships in the system are:

```text
users 1 ───── 0..1 driver_profiles
users 1 ───── 0..1 restaurants
users 1 ───── 0..* orders
users 1 ───── 0..* cart_items
users 1 ───── 0..* ratings

driver_profiles 1 ───── 0..* orders
driver_profiles 1 ───── 0..* driver_locations

restaurants 1 ───── 0..* menu_items
restaurants 1 ───── 0..* orders

categories 1 ───── 0..* menu_items

menu_items 1 ───── 0..* cart_items
menu_items 1 ───── 0..* order_items
menu_items 1 ───── 0..* ratings

orders 1 ───── 1..* order_items
orders 1 ───── 0..1 payments

order_items 1 ───── 0..1 ratings
```

---

## 5. Key Business Rules

1. A single `users` table stores all system users.
2. The `role` column determines the user's system role.
3. A driver has one `driver_profile`.
4. `orders.driver_id` references `driver_profiles.id`, not `users.id`.
5. A restaurant is managed by one Restaurant Manager.
6. A customer can have multiple cart items.
7. An order contains one or more order items.
8. Order items preserve the item name and price at the time of purchase.
9. A customer can submit a rating only for a purchased item.
10. Each purchased order item can have at most one rating.
11. Ratings must have a value from 1 to 5.
12. An order can have zero or one payment record.
13. Payment is handled through supported online payment methods.
14. Drivers do not collect cash from customers.
15. Driver location updates are stored as historical records.
16. The latest driver location represents the driver's latest known position.
17. Foreign keys enforce relationships between related records.
18. Appropriate indexes are used to improve common queries.

---

## 6. Database Integrity and Deletion Rules

Foreign key deletion behavior is selected according to the importance of preserving historical data.

- User records related to important historical transactions should generally be protected using `RESTRICT`.
- Driver assignment can be removed from an order using `SET NULL` without deleting the order.
- Deleting a restaurant removes its dependent menu items where appropriate.
- Deleting an order removes its order items and associated payment where appropriate.
- Deleting a driver profile removes its associated location history.
- Ratings remain associated with their purchased order items according to the selected foreign-key rules.

---

## 7. Implementation

The database will be implemented using:

- Laravel 12 migrations
- MySQL
- Eloquent ORM
- Foreign key constraints
- Database indexes
- Unique constraints
- Enum/status fields
- Validation at the application/API layer

The database design should remain synchronized with the ERD, API contract, Laravel migrations, and Eloquent model relationships throughout development.
