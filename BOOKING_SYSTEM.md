# نظام حجز الخدمات - Service Booking System

## نظرة عامة - Overview

تم إنشاء نظام متكامل لحجز الخدمات يسمح للعملاء المسجلين بحجز الخدمات مع إمكانية استخدام كوبونات الخصم والدفع عبر طرق دفع مختلفة.

A complete service booking system has been created that allows registered customers to book services with the ability to use discount coupons and pay through different payment methods.

## الجداول الجديدة - New Tables

### 1. جدول payments
يحتوي على معلومات الدفع لكل حجز:
- `booking_id`: معرف الحجز
- `payment_method`: طريقة الدفع (paymob, tabby, etc.)
- `transaction_id`: معرف المعاملة من بوابة الدفع
- `amount`: المبلغ المدفوع
- `currency`: العملة (افتراضي SAR)
- `status`: حالة الدفع (pending, completed, failed, refunded)
- `payment_response`: استجابة بوابة الدفع (JSON)

## API Endpoints

### 1. حساب السعر - Calculate Price
```
POST /api/v1/customers/services/calculate_price
```

**الغرض**: حساب السعر النهائي مع تطبيق الكوبون (إن وجد)

**المدخلات**:
```json
{
    "service_id": 1,
    "coupon_code": "DISCOUNT20" // اختياري
}
```

**المخرجات**:
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

---

### 2. بدء الحجز - Initiate Booking
```
POST /api/v1/customers/services/initiate_booking
```

**الغرض**: التحقق من البيانات وإعداد تفاصيل الحجز قبل الدفع

**المدخلات**:
```json
{
    "service_id": 1,
    "customer_address_id": 5,
    "order_date": "2026-02-25",
    "order_time": "14:30",
    "coupon_code": "DISCOUNT20" // اختياري
}
```

**المخرجات**:
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
        "final_price": 96.00,
        // ... باقي التفاصيل
    }
}
```

---

### 3. تأكيد الحجز - Confirm Booking
```
POST /api/v1/customers/services/confirm_booking
```

**الغرض**: تأكيد الحجز بعد إتمام الدفع وحفظ البيانات في قاعدة البيانات

**المدخلات**:
```json
{
    "service_id": 1,
    "customer_address_id": 5,
    "order_date": "2026-02-25",
    "order_time": "14:30",
    "coupon_code": "DISCOUNT20",
    "payment_method": "paymob",
    "transaction_id": "TXN123456789",
    "payment_response": "{\"status\": \"success\", \"transaction_id\": \"TXN123456789\"}"
}
```

**المخرجات**:
```json
{
    "status": true,
    "message": "Booking confirmed successfully",
    "data": {
        "booking": {
            "id": 1,
            "service_id": 1,
            "customer_id": 10,
            "order_status": "confirmed",
            // ... باقي التفاصيل
        },
        "payment": {
            "id": 1,
            "booking_id": 1,
            "payment_method": "paymob",
            "transaction_id": "TXN123456789",
            "amount": 96.00,
            "status": "completed"
        }
    }
}
```

---

### 4. حجوزاتي - My Bookings
```
GET /api/v1/customers/bookings
```

**الغرض**: عرض جميع حجوزات العميل مع التصفح

**المخرجات**:
```json
{
    "status": true,
    "message": "Bookings retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "service": { /* تفاصيل الخدمة */ },
                "customer_address": { /* تفاصيل العنوان */ },
                "payment": { /* تفاصيل الدفع */ },
                "order_status": "confirmed",
                // ... باقي التفاصيل
            }
        ],
        "per_page": 10,
        "total": 25
    }
}
```

---

### 5. تفاصيل الحجز - Booking Details
```
GET /api/v1/customers/bookings/{booking_id}
```

**الغرض**: عرض تفاصيل حجز معين

**المخرجات**:
```json
{
    "status": true,
    "message": "Booking details retrieved successfully",
    "data": {
        "id": 1,
        "service": { /* تفاصيل الخدمة */ },
        "customer_address": { /* تفاصيل العنوان */ },
        "payment": { /* تفاصيل الدفع */ },
        "order_date": "2026-02-25 14:30:00",
        "order_status": "confirmed"
    }
}
```

---

### 6. إلغاء الحجز - Cancel Booking
```
POST /api/v1/customers/bookings/{booking_id}/cancel
```

**الغرض**: إلغاء حجز معين (لا يمكن إلغاء الحجوزات المكتملة أو الملغاة مسبقاً)

**المخرجات**:
```json
{
    "status": true,
    "message": "Booking cancelled successfully",
    "data": {
        "id": 1,
        "order_status": "cancelled"
    }
}
```

## سير العمل - Workflow

### الطريقة المقترحة للحجز:

1. **حساب السعر** (اختياري):
   - استدعاء `calculate_price` لعرض السعر النهائي للعميل
   - يمكن تخطي هذه الخطوة والانتقال مباشرة للخطوة التالية

2. **بدء الحجز**:
   - استدعاء `initiate_booking` للتحقق من البيانات
   - الحصول على تفاصيل الحجز والسعر النهائي

3. **عملية الدفع**:
   - توجيه المستخدم لبوابة الدفع (Paymob, Tabby, etc.)
   - انتظار استجابة بوابة الدفع

4. **تأكيد الحجز**:
   - بعد نجاح الدفع، استدعاء `confirm_booking`
   - يتم حفظ الحجز والدفع في قاعدة البيانات

5. **عرض الحجوزات**:
   - يمكن للعميل عرض جميع حجوزاته عبر `myBookings`
   - أو عرض تفاصيل حجز معين عبر `bookingDetails`

## ملاحظات مهمة - Important Notes

1. **المرونة في طرق الدفع**:
   - النظام مصمم ليكون مرن ويدعم أي طريقة دفع
   - يتم تخزين `payment_method` و `transaction_id` و `payment_response`
   - يمكن التكامل مع Paymob, Tabby, أو أي بوابة دفع أخرى

2. **الكوبونات**:
   - يتم التحقق من صلاحية الكوبون (نشط وغير منتهي)
   - يتم حساب الخصم تلقائياً على جميع الأسعار

3. **العناوين**:
   - يجب أن يكون العنوان مسجلاً مسبقاً للعميل
   - النظام يتحقق من ملكية العنوان للعميل
   - النظام يتحقق من أن مقدم الخدمة يعمل في المدينة المختارة

4. **حالات الطلب**:
   - `pending`: في انتظار التأكيد
   - `confirmed`: تم التأكيد
   - `completed`: تم الإنجاز
   - `cancelled`: ملغي

5. **الأمان والتحققات**:
   - جميع الـ endpoints تتطلب تسجيل دخول
   - يتم التحقق من حالة العميل (CheckForCustomerStatus)
   - استخدام Database Transactions لضمان سلامة البيانات
   - التحقق من توفر الخدمة في منطقة العميل (المدينة)

## التطوير المستقبلي - Future Development

1. **تكامل بوابات الدفع**:
   - إضافة functions محددة لكل بوابة دفع
   - معالجة Webhooks من بوابات الدفع

2. **الإشعارات**:
   - إرسال إشعارات عند تأكيد الحجز
   - إرسال تذكيرات قبل موعد الخدمة

3. **التقييمات**:
   - السماح للعميل بتقييم الخدمة بعد الإنجاز

4. **الاسترجاع**:
   - إضافة نظام استرجاع الأموال للحجوزات الملغاة
