# أمثلة على استخدام API الحجز - Booking API Examples

## 1. حساب السعر - Calculate Price

### Request
```http
POST /api/v1/customers/services/calculate_price
Authorization: Bearer {customer_token}
Content-Type: application/json

{
    "service_id": 1,
    "coupon_code": "DISCOUNT20"
}
```

### Response (Success)
```json
{
    "status": true,
    "message": "Price calculated successfully",
    "data": {
        "price_before_discount": 120.00,
        "discount_amount": 24.00,
        "price_after_discount": 96.00
    }
}
```

### Response (Invalid Coupon)
```json
{
    "status": false,
    "message": "Invalid or expired coupon code"
}
```

### Response (Service Not Available in Area)
```json
{
    "status": false,
    "message": "This service is not available in your area"
}
```

---

## 2. بدء الحجز - Initiate Booking

### Request
```http
POST /api/v1/customers/services/initiate_booking
Authorization: Bearer {customer_token}
Content-Type: application/json

{
    "service_id": 1,
    "customer_address_id": 5,
    "order_date": "2026-02-25",
    "order_time": "14:30",
    "coupon_code": "DISCOUNT20"
}
```

### Response (Success)
```json
{
    "status": true,
    "message": "Booking details prepared. Proceed to payment.",
    "data": {
        "service_id": 1,
        "service_name": "Cleaning Service",
        "customer_address_id": 5,
        "address": "123 Main St, Riyadh",
        "order_date_time": "2026-02-25 14:30:00",
        "service_provider_price": 100.00,
        "sale_price": 120.00,
        "profit_amount": 20.00,
        "discount_percentage": 20,
        "discount_amount": 24.00,
        "service_provider_price_after_discount": 80.00,
        "sale_price_after_discount": 96.00,
        "profit_amount_after_discount": 16.00,
        "final_price": 96.00,
        "coupon_id": 1,
        "coupon_code": "DISCOUNT20"
    }
}
```

### Response (Validation Error)
```json
{
    "status": false,
    "message": "The order date field must be a date after or equal to today."
}
```

---

## 3. تأكيد الحجز - Confirm Booking

### Request (Paymob Example)
```http
POST /api/v1/customers/services/confirm_booking
Authorization: Bearer {customer_token}
Content-Type: application/json

{
    "service_id": 1,
    "customer_address_id": 5,
    "order_date": "2026-02-25",
    "order_time": "14:30",
    "coupon_code": "DISCOUNT20",
    "payment_method": "paymob",
    "transaction_id": "TXN_PAYMOB_123456789",
    "payment_response": "{\"success\": true, \"transaction_id\": \"TXN_PAYMOB_123456789\", \"amount\": 96.00}"
}
```

### Request (Tabby Example)
```http
POST /api/v1/customers/services/confirm_booking
Authorization: Bearer {customer_token}
Content-Type: application/json

{
    "service_id": 1,
    "customer_address_id": 5,
    "order_date": "2026-02-25",
    "order_time": "14:30",
    "payment_method": "tabby",
    "transaction_id": "TXN_TABBY_987654321",
    "payment_response": "{\"status\": \"approved\", \"payment_id\": \"TXN_TABBY_987654321\"}"
}
```

### Response (Success)
```json
{
    "status": true,
    "message": "Booking confirmed successfully",
    "data": {
        "booking": {
            "id": 1,
            "service_id": 1,
            "customer_id": 10,
            "coupon_id": 1,
            "customer_address_id": 5,
            "coupon_code": "DISCOUNT20",
            "discount_percentage": 20,
            "order_date": "2026-02-25T14:30:00.000000Z",
            "delivery_date": null,
            "order_status": "confirmed",
            "created_at": "2026-02-20T15:45:00.000000Z",
            "updated_at": "2026-02-20T15:45:00.000000Z",
            "converted_service_provider_price": 80.00,
            "converted_sale_price": 96.00,
            "converted_profit_amount": 16.00,
            "service": {
                "id": 1,
                "ar_name": "خدمة التنظيف",
                "en_name": "Cleaning Service"
            },
            "customer": {
                "id": 10,
                "name": "Ahmed Ali",
                "phone": "+966501234567"
            },
            "customer_address": {
                "id": 5,
                "address_title": "Home",
                "full_address": "123 Main St, Riyadh"
            }
        },
        "payment": {
            "id": 1,
            "booking_id": 1,
            "payment_method": "paymob",
            "transaction_id": "TXN_PAYMOB_123456789",
            "amount": 96.00,
            "currency": "SAR",
            "status": "completed",
            "payment_response": "{\"success\": true, \"transaction_id\": \"TXN_PAYMOB_123456789\", \"amount\": 96.00}",
            "created_at": "2026-02-20T15:45:00.000000Z",
            "updated_at": "2026-02-20T15:45:00.000000Z"
        }
    }
}
```

---

## 4. حجوزاتي - My Bookings

### Request
```http
GET /api/v1/customers/bookings?page=1
Authorization: Bearer {customer_token}
```

### Response
```json
{
    "status": true,
    "message": "Bookings retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "service_id": 1,
                "customer_id": 10,
                "order_date": "2026-02-25T14:30:00.000000Z",
                "order_status": "confirmed",
                "converted_sale_price_after_discount": 96.00,
                "service": {
                    "id": 1,
                    "ar_name": "خدمة التنظيف",
                    "en_name": "Cleaning Service"
                },
                "customer_address": {
                    "id": 5,
                    "address_title": "Home",
                    "full_address": "123 Main St, Riyadh"
                },
                "payment": {
                    "id": 1,
                    "payment_method": "paymob",
                    "amount": 96.00,
                    "status": "completed"
                }
            }
        ],
        "first_page_url": "http://localhost/api/v1/customers/bookings?page=1",
        "from": 1,
        "last_page": 3,
        "last_page_url": "http://localhost/api/v1/customers/bookings?page=3",
        "next_page_url": "http://localhost/api/v1/customers/bookings?page=2",
        "path": "http://localhost/api/v1/customers/bookings",
        "per_page": 10,
        "prev_page_url": null,
        "to": 10,
        "total": 25
    }
}
```

---

## 5. تفاصيل الحجز - Booking Details

### Request
```http
GET /api/v1/customers/bookings/1
Authorization: Bearer {customer_token}
```

### Response
```json
{
    "status": true,
    "message": "Booking details retrieved successfully",
    "data": {
        "id": 1,
        "service_id": 1,
        "customer_id": 10,
        "coupon_id": 1,
        "customer_address_id": 5,
        "coupon_code": "DISCOUNT20",
        "discount_percentage": 20,
        "order_date": "2026-02-25T14:30:00.000000Z",
        "delivery_date": null,
        "order_status": "confirmed",
        "created_at": "2026-02-20T15:45:00.000000Z",
        "updated_at": "2026-02-20T15:45:00.000000Z",
        "converted_service_provider_price_after_discount": 80.00,
        "converted_sale_price_after_discount": 96.00,
        "converted_profit_amount_after_discount": 16.00,
        "service": {
            "id": 1,
            "category_id": 1,
            "sub_category_id": 2,
            "service_provider_id": 5,
            "ar_name": "خدمة التنظيف",
            "en_name": "Cleaning Service",
            "ar_description": "خدمة تنظيف شاملة للمنازل",
            "en_description": "Comprehensive home cleaning service",
            "is_featured": true,
            "status": true,
            "average_rate": 4.5,
            "rates_count": 10
        },
        "customer": {
            "id": 10,
            "name": "Ahmed Ali",
            "phone": "+966501234567",
            "email": "ahmed@example.com"
        },
        "customer_address": {
            "id": 5,
            "customer_id": 10,
            "country_id": 1,
            "city_id": 1,
            "address_title": "Home",
            "full_address": "123 Main St, Riyadh",
            "is_default": true
        },
        "payment": {
            "id": 1,
            "booking_id": 1,
            "payment_method": "paymob",
            "transaction_id": "TXN_PAYMOB_123456789",
            "amount": 96.00,
            "currency": "SAR",
            "status": "completed",
            "payment_response": "{\"success\": true, \"transaction_id\": \"TXN_PAYMOB_123456789\", \"amount\": 96.00}",
            "created_at": "2026-02-20T15:45:00.000000Z",
            "updated_at": "2026-02-20T15:45:00.000000Z"
        }
    }
}
```

---

## 6. إلغاء الحجز - Cancel Booking

### Request
```http
POST /api/v1/customers/bookings/1/cancel
Authorization: Bearer {customer_token}
```

### Response (Success)
```json
{
    "status": true,
    "message": "Booking cancelled successfully",
    "data": {
        "id": 1,
        "service_id": 1,
        "customer_id": 10,
        "order_status": "cancelled",
        "order_date": "2026-02-25T14:30:00.000000Z",
        "updated_at": "2026-02-20T16:00:00.000000Z"
    }
}
```

### Response (Cannot Cancel)
```json
{
    "status": false,
    "message": "Cannot cancel this booking"
}
```

---

## سيناريو كامل - Complete Scenario

### الخطوة 1: حساب السعر (اختياري)
```bash
curl -X POST http://localhost/api/v1/customers/services/calculate_price \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "service_id": 1,
    "coupon_code": "DISCOUNT20"
  }'
```

### الخطوة 2: بدء الحجز
```bash
curl -X POST http://localhost/api/v1/customers/services/initiate_booking \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "service_id": 1,
    "customer_address_id": 5,
    "order_date": "2026-02-25",
    "order_time": "14:30",
    "coupon_code": "DISCOUNT20"
  }'
```

### الخطوة 3: معالجة الدفع (في التطبيق)
```javascript
// هنا يتم توجيه المستخدم لبوابة الدفع
// وانتظار استجابة الدفع
const paymentResponse = await processPayment({
  amount: 96.00,
  method: 'paymob'
});
```

### الخطوة 4: تأكيد الحجز
```bash
curl -X POST http://localhost/api/v1/customers/services/confirm_booking \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "service_id": 1,
    "customer_address_id": 5,
    "order_date": "2026-02-25",
    "order_time": "14:30",
    "coupon_code": "DISCOUNT20",
    "payment_method": "paymob",
    "transaction_id": "TXN_PAYMOB_123456789",
    "payment_response": "{\"success\": true}"
  }'
```

### الخطوة 5: عرض الحجوزات
```bash
curl -X GET http://localhost/api/v1/customers/bookings \
  -H "Authorization: Bearer {token}"
```

---

## ملاحظات للتطوير - Development Notes

### 1. التكامل مع Paymob
```php
// في المستقبل يمكن إضافة
public function processPaymobPayment(Request $request) {
    // معالجة الدفع عبر Paymob
    // إرجاع transaction_id و payment_response
}
```

### 2. التكامل مع Tabby
```php
// في المستقبل يمكن إضافة
public function processTabbyPayment(Request $request) {
    // معالجة الدفع عبر Tabby
    // إرجاع transaction_id و payment_response
}
```

### 3. Webhooks
```php
// لاستقبال إشعارات بوابات الدفع
public function paymobWebhook(Request $request) {
    // تحديث حالة الدفع
}

public function tabbyWebhook(Request $request) {
    // تحديث حالة الدفع
}
```
