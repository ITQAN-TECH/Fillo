# Order Cancellation Requests System Documentation

## Overview
A complete order cancellation request system has been added to allow customers to request order cancellations, and admins to review and approve/reject these requests.

## Created Files

### 1. Migration Files
- **`database/migrations/2026_02_25_190000_create_order_cancellation_requests_table.php`**
  - Creates `order_cancellation_requests` table
  - Fields: order_id, customer_id, customer_reason, status, cancellation_reason, admin_notes, reviewed_by, reviewed_at

### 2. Model
- **`app/Models/OrderCancellationRequest.php`**
  - Handles order cancellation request data
  - Relationships with Order, Customer, and Admin (reviewer)

### 3. Controllers

#### Customer Controller (Updated)
- **`app/Http/Controllers/api/v1/customers/OrderController.php`**
  - `requestCancellation($order_id)` - Create cancellation request
  - `myCancellationRequests()` - List customer's cancellation requests
  - `showCancellationRequest($request_id)` - Show cancellation request details

#### Admin Controller (New)
- **`app/Http/Controllers/api/v1/admin/OrderCancellationRequestController.php`**
  - `index()` - List all cancellation requests with search and filters
  - `show($request_id)` - Show cancellation request details
  - `approve($request_id)` - Approve cancellation request
  - `reject($request_id)` - Reject cancellation request

### 4. Notification Classes
- **`app/Notifications/customers/CancellationRequestApprovedNotification.php`** - Sent when admin approves request
- **`app/Notifications/customers/CancellationRequestRejectedNotification.php`** - Sent when admin rejects request

### 5. Permissions Seeder
- **`database/seeders/OrderCancellationRequestPermissionsSeeder.php`**
  - `show-order-cancellation-requests` - Permission to view cancellation requests
  - `edit-order-cancellation-requests` - Permission to manage cancellation requests

### 6. Translation Files Updated
Both Arabic and English translation files updated with all cancellation request messages.

### 7. Routes Updated

#### Customer Routes
```php
Route::post('orders/{order_id}/request_cancellation', [OrderController::class, 'requestCancellation']);
Route::get('cancellation_requests', [OrderController::class, 'myCancellationRequests']);
Route::get('cancellation_requests/{request_id}', [OrderController::class, 'showCancellationRequest']);
```

#### Admin Routes
```php
Route::get('order_cancellation_requests', [OrderCancellationRequestController::class, 'index']);
Route::get('order_cancellation_requests/{request_id}', [OrderCancellationRequestController::class, 'show']);
Route::post('order_cancellation_requests/{request_id}/approve', [OrderCancellationRequestController::class, 'approve']);
Route::post('order_cancellation_requests/{request_id}/reject', [OrderCancellationRequestController::class, 'reject']);
```

## Features Implemented

### 1. Customer Request Cancellation
- ✅ Customers can request cancellation for orders that are NOT:
  - completed
  - refunded
  - cancelled
- ✅ Customers can provide optional reason for cancellation request
- ✅ Only one pending cancellation request allowed per order
- ✅ View their cancellation requests list
- ✅ View individual cancellation request details

### 2. Admin Review Cancellation Requests
- ✅ View all cancellation requests with filters (pending/approved/rejected/all)
- ✅ Search by order number, phone, customer name/email/phone
- ✅ View request details with full order information
- ✅ Statistics dashboard showing counts by status

### 3. Approve Cancellation Request
When admin approves:
- ✅ Must provide cancellation reason (administrative or customer_not_received)
- ✅ Can provide admin notes
- ✅ Returns products to inventory
- ✅ Cancels the order with specified reason
- ✅ Calculates refund amount:
  - **administrative** → Full refund including shipping
  - **customer_not_received** → Refund without shipping fee
- ✅ Updates payment status to refunded
- ✅ Records reviewer (admin) and review time
- ✅ Sends approval notification to customer

### 4. Reject Cancellation Request
When admin rejects:
- ✅ Can provide admin notes explaining rejection
- ✅ Records reviewer (admin) and review time
- ✅ Sends rejection notification to customer
- ✅ Order continues with its current status

### 5. Validation Rules
- Customer can only request cancellation if order is not completed/refunded/cancelled
- Cannot create duplicate pending cancellation requests
- Only pending requests can be approved/rejected
- Admin must provide cancellation reason when approving

## Database Schema

### Order Cancellation Requests Table
- `id` (Primary Key)
- `order_id` (Foreign Key → orders)
- `customer_id` (Foreign Key → customers)
- `customer_reason` (text, nullable) - Customer's reason for requesting cancellation
- `status` (enum: pending, approved, rejected) - Request status
- `cancellation_reason` (enum: administrative, customer_not_received, nullable) - Admin's selected reason
- `admin_notes` (text, nullable) - Admin's notes about the decision
- `reviewed_by` (Foreign Key → users, nullable) - Admin who reviewed
- `reviewed_at` (timestamp, nullable) - When review happened
- `created_at` (timestamp)
- `updated_at` (timestamp)

## API Endpoints

### Customer Endpoints

#### Request Order Cancellation
```
POST /api/v1/customers/orders/{order_id}/request_cancellation
Body:
  - customer_reason: nullable|string|max:500
```

#### List My Cancellation Requests
```
GET /api/v1/customers/cancellation_requests
```

#### Show Cancellation Request Details
```
GET /api/v1/customers/cancellation_requests/{request_id}
```

### Admin Endpoints

#### List All Cancellation Requests
```
GET /api/v1/dashboard/order_cancellation_requests
Query Parameters:
  - search: string (searches in order_number, phone, customer name/email/phone)
  - status: pending|approved|rejected|all
```

#### Show Cancellation Request Details
```
GET /api/v1/dashboard/order_cancellation_requests/{request_id}
```

#### Approve Cancellation Request
```
POST /api/v1/dashboard/order_cancellation_requests/{request_id}/approve
Body:
  - cancellation_reason: required|in:administrative,customer_not_received
  - admin_notes: nullable|string
```

#### Reject Cancellation Request
```
POST /api/v1/dashboard/order_cancellation_requests/{request_id}/reject
Body:
  - admin_notes: nullable|string
```

## Installation Steps

1. **Run Migrations:**
```bash
php artisan migrate
```

2. **Run Seeders:**
```bash
php artisan db:seed --class=OrderCancellationRequestPermissionsSeeder
# OR run all seeders
php artisan db:seed
```

3. **Clear Cache:**
```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

## Workflow Example

### Customer Side:
1. Customer places an order
2. Customer changes their mind and requests cancellation
3. System creates a cancellation request with status "pending"
4. Customer receives notification when admin reviews the request

### Admin Side:
1. Admin views all pending cancellation requests
2. Admin reviews the request and order details
3. Admin decides to approve or reject:
   
   **If Approving:**
   - Selects cancellation reason (administrative or customer_not_received)
   - Adds optional admin notes
   - System cancels order, refunds amount, returns products to inventory
   - Customer receives approval notification with refund details
   
   **If Rejecting:**
   - Adds optional admin notes explaining why
   - Order continues with current status
   - Customer receives rejection notification

## Business Logic

### Refund Calculation on Approval:
- **Administrative Reason:** Customer receives full refund including shipping fee
- **Customer Not Received:** Customer receives refund excluding shipping fee

### Inventory Management:
- Products are returned to inventory only when cancellation is approved
- Product quantities restored to their variants

### Status Flow:
```
Cancellation Request: pending → approved (order cancelled) → refunded payment
                              → rejected (order continues)
```

## Notes

1. **One Request Per Order:** Only one pending cancellation request allowed per order to prevent confusion
2. **Order Status Check:** System validates order can be cancelled before creating request
3. **Audit Trail:** System tracks who reviewed the request and when
4. **Notifications:** Both approval and rejection send notifications to customer
5. **Permissions:** Admins need proper permissions to view and manage cancellation requests

## Error Messages

All error messages are translated in both Arabic and English:
- Cannot request cancellation for this order
- Cancellation request already exists
- Cancellation request is not pending
- Permission errors

## Success!

The complete order cancellation request system is now ready. Customers can request cancellations, and admins can review and manage these requests with full control over the cancellation process and refund logic.
