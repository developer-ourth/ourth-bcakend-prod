# Phase 1 API - Vendor Onboarding & KYC - Complete Guide

## 📋 API Endpoints Overview

```
POST   /api/vendors/register                      - Register new vendor
POST   /api/vendors/kyc/upload                    - Upload KYC document
GET    /api/vendors/{vendor}/approval-status      - Check approval status
GET    /api/vendors/{vendor}/qr                   - Get QR code
```

---

## 1. Vendor Registration

### Endpoint
```
POST /api/vendors/register
```

### Request Headers
```
Content-Type: application/json
Accept: application/json
```

### Request Body (JSON)
```json
{
  "name": "Rajesh Kumar",
  "email": "rajesh@example.com",
  "phone": "919876543210",
  "password": "SecurePass123",
  "business_name": "Kumar Electronics Store",
  "gstin": "27AABCT1234H1Z0",
  "trade_license_number": "TL123456789",
  "address_line1": "Shop No. 5, Market Street",
  "city": "Mumbai",
  "state": "Maharashtra",
  "postal_code": "400001"
}
```

### Validation Rules
```
name                     : required | string | max:255
email                    : required | email | unique:users
phone                    : required | string | max:20 | unique:users
password                 : required | string | min:6
business_name            : required | string | max:255
gstin                    : required | string | size:15 | unique:vendors
trade_license_number     : required | string
address_line1            : required | string
city                     : required | string
state                    : required | string
postal_code              : required | string | max:10
```

### Success Response (201 Created)
```json
{
  "user": {
    "id": 1,
    "name": "Rajesh Kumar",
    "email": "rajesh@example.com",
    "phone": "919876543210",
    "user_type": "vendor",
    "status": "active",
    "created_at": "2026-04-20T10:30:00Z",
    "updated_at": "2026-04-20T10:30:00Z"
  },
  "vendor": {
    "id": 1,
    "user_id": 1,
    "business_name": "Kumar Electronics Store",
    "gstin": "27AABCT1234H1Z0",
    "trade_license_number": "TL123456789",
    "address_line1": "Shop No. 5, Market Street",
    "city": "Mumbai",
    "state": "Maharashtra",
    "postal_code": "400001",
    "kyc_status": null,
    "created_at": "2026-04-20T10:30:00Z",
    "updated_at": "2026-04-20T10:30:00Z"
  }
}
```

### Error Responses

**Validation Error (422 Unprocessable Entity)**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "phone": ["The phone has already been taken."],
    "gstin": ["The gstin has already been taken."]
  }
}
```

**Invalid GSTIN (422 Unprocessable Entity)**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "gstin": ["The gstin must be 15 characters."]
  }
}
```

### cURL Example
```bash
curl -X POST http://localhost:8000/api/vendors/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Rajesh Kumar",
    "email": "rajesh@example.com",
    "phone": "919876543210",
    "password": "SecurePass123",
    "business_name": "Kumar Electronics Store",
    "gstin": "27AABCT1234H1Z0",
    "trade_license_number": "TL123456789",
    "address_line1": "Shop No. 5, Market Street",
    "city": "Mumbai",
    "state": "Maharashtra",
    "postal_code": "400001"
  }'
```

### Postman Collection
```
1. Create new POST request
2. URL: http://localhost:8000/api/vendors/register
3. Headers:
   - Content-Type: application/json
   - Accept: application/json
4. Body (raw JSON): Copy from above
5. Click Send
```

---

## 2. Upload KYC Document

### Endpoint
```
POST /api/vendors/kyc/upload
```

### Request Body (JSON)
```json
{
  "vendor_id": 1,
  "document_type": "gst_certificate",
  "document_url": "https://example.com/documents/gst_certificate.pdf"
}
```

### Validation Rules
```
vendor_id       : required | integer | exists:vendors,id
document_type   : required | string (gst_certificate, trade_license, pan_card, aadhar, bank_statement)
document_url    : required | url
```

### Accepted Document Types
- `gst_certificate` - GST Certificate
- `trade_license` - Trade License
- `pan_card` - PAN Card
- `aadhar` - Aadhar Card
- `bank_statement` - Bank Statement

### Success Response (201 Created)
```json
{
  "id": 1,
  "vendor_id": 1,
  "document_type": "gst_certificate",
  "document_url": "https://example.com/documents/gst_certificate.pdf",
  "status": "submitted",
  "verified_at": null,
  "verified_by": null,
  "created_at": "2026-04-20T10:35:00Z",
  "updated_at": "2026-04-20T10:35:00Z"
}
```

### Error Responses

**Vendor Not Found (422)**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "vendor_id": ["The selected vendor_id is invalid."]
  }
}
```

**Invalid URL (422)**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "document_url": ["The document_url must be a valid URL."]
  }
}
```

### cURL Example
```bash
curl -X POST http://localhost:8000/api/vendors/kyc/upload \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "vendor_id": 1,
    "document_type": "gst_certificate",
    "document_url": "https://example.com/documents/gst_certificate.pdf"
  }'
```

### Multiple Document Uploads (Sequential)
```bash
# Upload GST Certificate
curl -X POST http://localhost:8000/api/vendors/kyc/upload \
  -H "Content-Type: application/json" \
  -d '{"vendor_id": 1, "document_type": "gst_certificate", "document_url": "https://example.com/gst.pdf"}'

# Upload Trade License
curl -X POST http://localhost:8000/api/vendors/kyc/upload \
  -H "Content-Type: application/json" \
  -d '{"vendor_id": 1, "document_type": "trade_license", "document_url": "https://example.com/license.pdf"}'

# Upload PAN Card
curl -X POST http://localhost:8000/api/vendors/kyc/upload \
  -H "Content-Type: application/json" \
  -d '{"vendor_id": 1, "document_type": "pan_card", "document_url": "https://example.com/pan.pdf"}'
```

---

## 3. Check Approval Status

### Endpoint
```
GET /api/vendors/{vendor}/approval-status
```

### URL Parameters
```
vendor : required | integer (vendor ID)
```

### Example URL
```
http://localhost:8000/api/vendors/1/approval-status
```

### Success Response (200 OK)
```json
{
  "vendor_id": 1,
  "approval_stage": "pending_documents",
  "updated_at": "2026-04-20T10:30:00Z"
}
```

### Approval Stages
```
pending_documents       - Awaiting document submission
documents_submitted     - Documents received, waiting for admin review
under_review            - Admin is reviewing documents
address_verification    - Address verification in progress
approved                - Vendor approved (QR code generated)
rejected                - Vendor application rejected
```

### Example Response After Approval
```json
{
  "vendor_id": 1,
  "approval_stage": "approved",
  "updated_at": "2026-04-20T11:00:00Z"
}
```

### cURL Example
```bash
curl -X GET http://localhost:8000/api/vendors/1/approval-status \
  -H "Accept: application/json"
```

---

## 4. Get QR Code

### Endpoint
```
GET /api/vendors/{vendor}/qr
```

### URL Parameters
```
vendor : required | integer (vendor ID)
```

### Example URL
```
http://localhost:8000/api/vendors/1/qr
```

### Success Response (200 OK) - QR Code Generated
```json
{
  "qr_code_url": "https://example.com/qr-codes/vendor_1_qr.png"
}
```

### Error Response (404 Not Found) - QR Not Ready
```json
{
  "message": "QR code not generated yet."
}
```

### cURL Example
```bash
curl -X GET http://localhost:8000/api/vendors/1/qr \
  -H "Accept: application/json"
```

---

## Complete Workflow Example

### Step 1: Register Vendor
```bash
curl -X POST http://localhost:8000/api/vendors/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Priya Sharma",
    "email": "priya@example.com",
    "phone": "919876543211",
    "password": "SecurePass123",
    "business_name": "Sharma Fashion Store",
    "gstin": "27AABCT1234H1Z1",
    "trade_license_number": "TL123456790",
    "address_line1": "Shop 10, Fashion Plaza",
    "city": "Delhi",
    "state": "Delhi",
    "postal_code": "110001"
  }'
```
**Response:** `vendor_id = 2`

### Step 2: Upload KYC Documents
```bash
# GST Certificate
curl -X POST http://localhost:8000/api/vendors/kyc/upload \
  -H "Content-Type: application/json" \
  -d '{"vendor_id": 2, "document_type": "gst_certificate", "document_url": "https://example.com/docs/gst.pdf"}'

# Trade License
curl -X POST http://localhost:8000/api/vendors/kyc/upload \
  -H "Content-Type: application/json" \
  -d '{"vendor_id": 2, "document_type": "trade_license", "document_url": "https://example.com/docs/license.pdf"}'
```

### Step 3: Check Approval Status
```bash
curl -X GET http://localhost:8000/api/vendors/2/approval-status
```
**Response:** `approval_stage: "pending_documents"`

### Step 4: Admin Approves (via database or separate admin API)
Once admin approves and QR is generated...

### Step 5: Retrieve QR Code
```bash
curl -X GET http://localhost:8000/api/vendors/2/qr
```
**Response:** `qr_code_url: "https://example.com/qr-codes/vendor_2_qr.png"`

---

## Testing with Postman

### Import Collection
1. Open Postman
2. Click "Import"
3. Select "Paste Raw Text"
4. Paste this:

```json
{
  "info": {
    "name": "OURTH Phase 1 - Vendor Onboarding",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Register Vendor",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "body": {
          "mode": "raw",
          "raw": "{\"name\": \"Rajesh Kumar\", \"email\": \"rajesh@example.com\", \"phone\": \"919876543210\", \"password\": \"SecurePass123\", \"business_name\": \"Kumar Electronics\", \"gstin\": \"27AABCT1234H1Z0\", \"trade_license_number\": \"TL123456789\", \"address_line1\": \"Shop 5, Market St\", \"city\": \"Mumbai\", \"state\": \"Maharashtra\", \"postal_code\": \"400001\"}"
        },
        "url": {"raw": "http://localhost:8000/api/vendors/register", "protocol": "http", "host": ["localhost"], "port": "8000", "path": ["api", "vendors", "register"]}
      }
    },
    {
      "name": "Upload KYC",
      "request": {
        "method": "POST",
        "header": [{"key": "Content-Type", "value": "application/json"}],
        "body": {
          "mode": "raw",
          "raw": "{\"vendor_id\": 1, \"document_type\": \"gst_certificate\", \"document_url\": \"https://example.com/gst.pdf\"}"
        },
        "url": {"raw": "http://localhost:8000/api/vendors/kyc/upload", "protocol": "http", "host": ["localhost"], "port": "8000", "path": ["api", "vendors", "kyc", "upload"]}
      }
    },
    {
      "name": "Check Approval Status",
      "request": {
        "method": "GET",
        "url": {"raw": "http://localhost:8000/api/vendors/1/approval-status", "protocol": "http", "host": ["localhost"], "port": "8000", "path": ["api", "vendors", "1", "approval-status"]}
      }
    },
    {
      "name": "Get QR Code",
      "request": {
        "method": "GET",
        "url": {"raw": "http://localhost:8000/api/vendors/1/qr", "protocol": "http", "host": ["localhost"], "port": "8000", "path": ["api", "vendors", "1", "qr"]}
      }
    }
  ]
}
```

---

## Common Validation Errors & Fixes

| Error | Cause | Fix |
|-------|-------|-----|
| `email has already been taken` | Email already registered | Use unique email |
| `phone has already been taken` | Phone already registered | Use unique phone |
| `gstin has already been taken` | GSTIN already registered | Use unique GSTIN |
| `gstin must be 15 characters` | GSTIN incorrect format | GSTIN should be 15 chars (e.g., `27AABCT1234H1Z0`) |
| `vendor_id is invalid` | Vendor doesn't exist | Check vendor ID exists |
| `document_url must be a valid URL` | Invalid URL format | Use valid URL with http:// or https:// |

---

## Testing Locally

### Start Laravel Server
```bash
php artisan serve
```
Server runs on: `http://localhost:8000`

### Test with cURL
All curl examples above will work with the server running.

### Test with Postman
1. Import collection (see section above)
2. Ensure server is running
3. Send requests

---

## Next Steps

After testing Phase 1 APIs:
1. Create Admin approval endpoints (approve/reject vendors)
2. Implement QR code generation on approval
3. Create Phase 2 APIs (Products, Orders, Shopping Cart)
4. Add authentication & authorization
5. Create frontend integration

---
