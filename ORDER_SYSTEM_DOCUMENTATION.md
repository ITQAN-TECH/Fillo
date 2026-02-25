# Order Management System Documentation

## Overview
A complete order management system has been created for your Fillo application with customer and admin functionality.

## Created Files

### 1. Migration Files
- **`database/migrations/2026_02_25_180000_add_cancellation_reason_to_orders_table.php`**
  - Adds `cancellation_reason` field (enum: administrative, customer_not_received)
  - Adds `admin_notes` field for admin comments

### 2. Controllers

#### Customer Controller
- **`app/Http/Controllers/api/v1/customers/OrderController.php`**
  - `index()` - List customer's orders with optional status filter
  - `show($order_id)` - Show order details
  - `store()` - Create new order from cart items

#### Admin Controller
- **`app/Http/Controllers/api/v1/admin/OrderController.php`**
  - `index()` - List all orders with search and filters
  - `show($order_id)` - Show order details
  - `confirmOrder($order_id)` - Approve pending order
  - `rejectOrder($order_id)` - Reject pending order with full refund
  - `shipOrder($order_id)` - Mark order as shipped
  - `deliverOrder($order_id)` - Mark order as delivered
  - `completeOrder($order_id)` - Mark order as completed
  - `cancelOrder($order_id)` - Cancel order with reason (administrative or customer_not_received)
  - `refundOrder($order_id)` - Refund completed order

### 3. Notification Classes
All notifications are stored in `app/Notifications/customers/`:
- `OrderConfirmedNotification.php` - Sent when admin confirms order
- `OrderRejectedNotification.php` - Sent when admin rejects order
- `OrderShippedNotification.php` - Sent when order is shipped
- `OrderDeliveredNotification.php` - Sent when order is delivered
- `OrderCompletedNotification.php` - Sent when order is completed
- `OrderCancelledNotification.php` - Sent when order is cancelled
- `OrderRefundedNotification.php` - Sent when order is refunded

### 4. Permissions Seeder
- **`database/seeders/OrderPermissionsSeeder.php`**
  - `show-orders` - Permission to view orders
  - `edit-orders` - Permission to manage orders

### 5. Translation Files Updated
Both Arabic and English translation files have been updated with all order-related messages:
- `resources/lang/ar/responses.php`
- `resources/lang/en/responses.php`

### 6. Routes Updated

#### Customer Routes (`routes/v1/api-customers.php`)
```php
// Order Routes
Route::get('orders', [OrderController::class, 'index']);
Route::get('orders/{order_id}', [OrderController::class, 'show']);
Route::post('orders', [OrderController::class, 'store']);
```

#### Admin Routes (`routes/v1/api-dashboard.php`)
```php
// Order Routes
Route::get('orders', [OrderController::class, 'index']);
Route::get('orders/{order_id}', [OrderController::class, 'show']);
Route::post('orders/{order_id}/confirm', [OrderController::class, 'confirmOrder']);
Route::post('orders/{order_id}/reject', [OrderController::class, 'rejectOrder']);
Route::post('orders/{order_id}/ship', [OrderController::class, 'shipOrder']);
Route::post('orders/{order_id}/deliver', [OrderController::class, 'deliverOrder']);
Route::post('orders/{order_id}/complete', [OrderController::class, 'completeOrder']);
Route::post('orders/{order_id}/cancel', [OrderController::class, 'cancelOrder']);
Route::post('orders/{order_id}/refund', [OrderController::class, 'refundOrder']);
```

## Features Implemented

### 1. Customer Order Creation
- ✅ Validates that customer has `national_address_short_number` filled
- ✅ Converts cart items to order
- ✅ Applies coupon if provided
- ✅ Calculates shipping fee from settings
- ✅ Decreases product quantities from inventory
- ✅ Creates order items for each cart item
- ✅ Clears cart after successful order creation
- ✅ Creates payment record
- ✅ Generates unique order number (ORD-XXXXX)

### 2. Order Status Flow
The order follows this status progression:
1. **pending** - Initial status when order is created
2. **confirmed** - Admin approves the order
3. **shipping** - Order is being shipped
4. **delivered** - Order has been delivered
5. **completed** - Order is finalized
6. **cancelled** - Order was cancelled
7. **refunded** - Order was refunded after completion

### 3. Admin Order Management

#### Confirm Order
- Changes status from `pending` to `confirmed`
- Sends notification to customer

#### Reject Order
- Only works on `pending` orders
- Returns products to inventory
- Sets status to `cancelled` with reason `administrative`
- Updates payment status to `refunded`
- Sends notification to customer

#### Ship Order
- Changes status from `confirmed` to `shipping`
- Sends notification to customer

#### Deliver Order
- Changes status from `shipping` to `delivered`
- Sends notification to customer

#### Complete Order
- Changes status from `delivered` to `completed`
- Sends notification to customer

#### Cancel Order
- Cannot cancel if status is `cancelled`, `completed`, or `refunded`
- Returns products to inventory
- Requires cancellation reason:
  - **administrative** - Full refund including shipping
  - **customer_not_received** - Refund without shipping fee
- Updates payment status to `refunded`
- Sends notification to customer

#### Refund Order
- Only works on `completed` orders
- Returns products to inventory
- Changes status to `refunded`
- Updates payment status to `refunded`
- Sends notification to customer

### 4. Validation Rules
- Customer must have `national_address_short_number` to place order
- Cart must not be empty
- Product variants must be available and have sufficient quantity
- Admin can only perform actions based on current order status
- Order cannot be cancelled if already cancelled, completed, or refunded

### 5. Notifications
All notifications are sent using the same pattern as your booking system:
- Database notifications
- FCM push notifications
- Bilingual messages (Arabic and English)

### 6. Inventory Management
- Product quantities are decreased when order is created
- Product quantities are restored when order is:
  - Rejected
  - Cancelled
  - Refunded

## Installation Steps

1. **Run Migrations:**
```bash
php artisan migrate
```

2. **Run Seeders:**
```bash
php artisan db:seed --class=OrderPermissionsSeeder
# OR run all seeders
php artisan db:seed
```

3. **Clear Cache:**
```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

## API Endpoints

### Customer Endpoints

#### List Orders
```
GET /api/v1/customers/orders
Query Parameters:
  - status: pending|confirmed|shipping|delivered|completed|cancelled|refunded|all
```

#### Show Order Details
```
GET /api/v1/customers/orders/{order_id}
```

#### Create Order
```
POST /api/v1/customers/orders
Body:
  - customer_address_id: required|exists:customer_addresses,id
  - coupon_code: nullable|string|exists:coupons,code
```

### Admin Endpoints

#### List Orders
```
GET /api/v1/dashboard/orders
Query Parameters:
  - search: string (searches in order_number, phone, customer name/email/phone)
  - status: pending|confirmed|shipping|delivered|completed|cancelled|refunded|all
```

#### Show Order Details
```
GET /api/v1/dashboard/orders/{order_id}
```

#### Confirm Order
```
POST /api/v1/dashboard/orders/{order_id}/confirm
```

#### Reject Order
```
POST /api/v1/dashboard/orders/{order_id}/reject
```

#### Ship Order
```
POST /api/v1/dashboard/orders/{order_id}/ship
```

#### Deliver Order
```
POST /api/v1/dashboard/orders/{order_id}/deliver
```

#### Complete Order
```
POST /api/v1/dashboard/orders/{order_id}/complete
```

#### Cancel Order
```
POST /api/v1/dashboard/orders/{order_id}/cancel
Body:
  - cancellation_reason: required|in:administrative,customer_not_received
  - admin_notes: nullable|string
```

#### Refund Order
```
POST /api/v1/dashboard/orders/{order_id}/refund
```

## Database Schema

### Orders Table
- `id`
- `customer_id`
- `customer_address_id`
- `country_id`
- `city_id`
- `full_address`
- `phone`
- `national_address_short_number`
- `coupon_id`
- `coupon_code`
- `order_number` (unique)
- `subtotal_price`
- `discount_percentage`
- `discount_amount`
- `subtotal_price_after_discount`
- `shipping_fee`
- `total_price`
- `order_status` (enum)
- `cancellation_reason` (enum) - NEW
- `admin_notes` (text) - NEW
- `created_at`
- `updated_at`

### Order Items Table
- `id`
- `order_id`
- `product_id`
- `product_variant_id`
- `price`
- `quantity`
- `total_price`
- `created_at`
- `updated_at`

## Notes

1. **Payment Gateway Integration**: The payment system is prepared but not connected to an actual payment gateway. Payment records are created with status 'pending'.

2. **Refund Logic**: Refunds update the database status but don't actually process payment refunds through a gateway.

3. **Notifications**: All notifications follow your existing notification pattern using `SendNotificationJob`.

4. **Permissions**: Admin users need `show-orders` permission to view orders and `edit-orders` permission to manage them.

5. **Transaction Safety**: All database operations use transactions to ensure data integrity.

6. **Currency Conversion**: The system respects your existing currency conversion facade for displaying prices.

## Error Messages

All error messages are properly translated in both Arabic and English:
- Cart is empty
- National address short number is required
- Product variant is not available
- Product variant quantity is not available
- Order status validation messages
- Permission errors

## Success!

The complete order management system is now ready to use. All features have been implemented according to your requirements with proper validation, notifications, inventory management, and refund logic.
