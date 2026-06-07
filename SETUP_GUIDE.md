# OURTH APP - DATABASE SETUP GUIDE

## 🚀 Quick Start (5 minutes)

### Prerequisites
```
✅ Laravel 11+ installed
✅ PostgreSQL 12+ installed locally
✅ Composer installed
```

### Step 1: Set Up Local Database (PostgreSQL)

```bash
# Create database locally
psql -U postgres

# In PostgreSQL prompt:
CREATE DATABASE ourth_dev;
CREATE USER ourth WITH PASSWORD 'dev_password';
ALTER ROLE ourth WITH CREATEDB;
GRANT ALL PRIVILEGES ON DATABASE ourth_dev TO ourth;
\q
```

### Step 2: Configure Laravel

```bash
# Update .env file
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ourth_dev
DB_USERNAME=ourth
DB_PASSWORD=dev_password
```

### Step 3: Create Laravel Project with Migrations

```bash
# Create new Laravel project
laravel new ourth-app
cd ourth-app

# Copy migration files from ourth_migrations.php to:
# database/migrations/

# Run migrations
php artisan migrate

# Create seeder for initial data
php artisan make:seeder UserSeeder
php artisan make:seeder VendorSeeder
php artisan make:seeder ProductSeeder

# Run seeders
php artisan db:seed
```

### Step 4: Verify Installation

```bash
# Test database connection
php artisan tinker

>>> DB::connection()->getPdo();
>>> Order::count();
>>> Vendor::count();
```

---

## 📊 Entity Relationship Diagram (Visual)

### ASCII Diagram

```
                                    ┌─────────────┐
                                    │    USERS    │
                                    └──────┬──────┘
                    ┌───────────────────────┼───────────────────────┐
                    │                       │                       │
                    ▼                       ▼                       ▼
            ┌──────────────┐        ┌─────────────────┐      ┌──────────────────┐
            │   VENDORS    │        │ DELIVERY        │      │ WAREHOUSE STAFF  │
            │              │        │ PARTNERS        │      │                  │
            └──────┬───────┘        └────────┬────────┘      └──────────────────┘
                   │                        │
        ┌──────────┼──────────┐             │
        │          │          │             │
        ▼          ▼          ▼             ▼
    ┌──────────┐┌────────┐┌─────────┐  ┌────────────┐
    │PRODUCTS  ││SETTINGS││KYC DOCS │  │DELIVERIES  │
    └──────┬───┘└────────┘└─────────┘  │            │
           │                            │  ┌──────┬──┴────┐
           ▼                            │  │      │       │
        ┌──────────┐                    ▼  ▼      ▼       ▼
        │INVENTORY │              ┌──────────────────────────────┐
        │   &      │              │ DELIVERY_LOCATIONS           │
        │STOCK     │              │ (High-Volume Real-time)      │
        │MOVEMENTS │              └──────────────────────────────┘
        └──────────┘
             │
             ▼
        ┌──────────┐
        │  ORDERS  │
        └────┬─────┘
             │
        ┌────┴─────────┬──────────┐
        │              │          │
        ▼              ▼          ▼
    ┌─────────┐  ┌──────────┐ ┌────────┐
    │ORDER    │  │PAYMENTS  │ │INVOICES│
    │ITEMS    │  │          │ │        │
    └─────────┘  └──────────┘ └────────┘
        │              │
        │              ▼
        │         ┌──────────┐
        │         │ REFUNDS  │
        │         │          │
        │         └──────────┘
        │
        ▼
    ┌──────────────┐
    │ RATINGS &    │
    │ REVIEWS      │
    └──────────────┘


SUPPORTING TABLES:
- NOTIFICATIONS, NOTIFICATION_LOGS
- AUDIT_LOGS, FEATURE_FLAGS
- VENDOR_DAILY_STATS (pre-computed analytics)
```

---

## 🏗️ Table Dependency Order

When creating tables, follow this order:

```
1. USERS (no dependencies)
2. VENDORS (depends on USERS)
3. VENDOR_KYC_DOCUMENTS (depends on VENDORS)
4. VENDOR_SETTINGS (depends on VENDORS)
5. PRODUCTS (depends on VENDORS)
6. INVENTORY (depends on PRODUCTS)
7. STOCK_MOVEMENTS (depends on INVENTORY, PRODUCTS)
8. ORDERS (depends on VENDORS)
9. ORDER_ITEMS (depends on ORDERS, PRODUCTS)
10. DELIVERIES (depends on ORDERS, USERS)
11. DELIVERY_LOCATIONS (depends on DELIVERIES)
12. DELIVERY_ROUTES (depends on USERS)
13. PAYMENTS (depends on ORDERS)
14. INVOICES (depends on ORDERS, VENDORS)
15. REFUNDS (depends on ORDERS, PAYMENTS)
16. RETURNS (depends on ORDERS, REFUNDS)
17. RATINGS (no dependency - polymorphic)
18. NOTIFICATIONS (depends on USERS)
19. NOTIFICATION_LOGS (depends on NOTIFICATIONS)
20. AUDIT_LOGS (depends on USERS - optional)
21. VENDOR_DAILY_STATS (computed from ORDERS)
```

---

## 📐 Data Size Estimates

### Per 1,000 Vendors:

| Table | 1 Month | 3 Months | 6 Months | 1 Year |
|-------|---------|----------|----------|---------|
| orders | 300 MB | 900 MB | 1.8 GB | 3.6 GB |
| order_items | 100 MB | 300 MB | 600 MB | 1.2 GB |
| delivery_locations | 2 GB | 6 GB | 12 GB | 24 GB |
| stock_movements | 50 MB | 150 MB | 300 MB | 600 MB |
| payments | 50 MB | 150 MB | 300 MB | 600 MB |
| notifications | 100 MB | 300 MB | 600 MB | 1.2 GB |
| **TOTAL** | **2.6 GB** | **7.8 GB** | **15.6 GB** | **31.2 GB** |

⚠️ **Important**: delivery_locations grows fastest. After 6 months with 1000 vendors, it needs partitioning.

---

## 🗃️ Creating Models

Create Laravel models for each table:

```bash
# Generate models with factories and migrations
php artisan make:model Vendor -mf
php artisan make:model Product -mf
php artisan make:model Order -mf
php artisan make:model OrderItem -mf
php artisan make:model Delivery -mf
php artisan make:model DeliveryLocation -mf
php artisan make:model Payment -mf
php artisan make:model Invoice -mf
php artisan make:model Rating -mf
php artisan make:model Notification -mf
```

### Example Model Structure

```php
// app/Models/Vendor.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model {
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'user_id', 'business_name', 'business_category', 'gstin',
        'trade_license_number', 'kyc_status', 'address_line1',
        'city', 'state', 'postal_code', 'latitude', 'longitude'
    ];
    
    protected $casts = [
        'kyc_verified_at' => 'datetime',
        'average_rating' => 'float',
        'total_revenue' => 'decimal:2'
    ];
    
    // Relationships
    public function user() {
        return $this->belongsTo(User::class);
    }
    
    public function products() {
        return $this->hasMany(Product::class);
    }
    
    public function orders() {
        return $this->hasMany(Order::class);
    }
    
    public function kycDocuments() {
        return $this->hasMany(VendorKycDocument::class);
    }
    
    public function settings() {
        return $this->hasOne(VendorSettings::class);
    }
    
    // Scopes
    public function scopeVerified($query) {
        return $query->where('kyc_status', 'verified');
    }
    
    public function scopeActive($query) {
        return $query->whereNull('deleted_at');
    }
}
```

---

## 🔧 Database Optimization Checklist

### At Month 0 (MVP Launch)
```
✅ Create all indexes from migrations
✅ Enable query logging
✅ Set up slow query log
✅ Monitor disk space
✅ Test backup/restore
```

### At Month 1
```
✅ Run ANALYZE weekly to update statistics
✅ Monitor slow queries from logs
✅ Check index usage
```

### At Month 3 (First Growth Phase)
```
✅ Review and optimize slow queries
✅ Consider adding read replicas
✅ Cache frequently accessed data
```

### At Month 6 (Scale Phase)
```
✅ Partition delivery_locations by month
✅ Archive old audit logs
✅ Implement materialized views for reports
✅ Add connection pooling (PgBouncer)
```

### At Month 12+ (Enterprise Phase)
```
✅ Shard data by vendor_id if needed
✅ Separate databases for analytics
✅ Implement CQRS pattern
```

---

## 📡 Backup Strategy

### Daily Backups
```bash
# Automated daily backup script
#!/bin/bash
BACKUP_DIR="/backups/ourth"
DATE=$(date +%Y%m%d_%H%M%S)

pg_dump -U ourth ourth_app | gzip > $BACKUP_DIR/backup_$DATE.sql.gz

# Keep only last 7 days
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +7 -delete

# Upload to S3
aws s3 cp $BACKUP_DIR/backup_$DATE.sql.gz s3://ourth-backups/
```

### Weekly Full Backup
```bash
pg_dump --format=custom -U ourth ourth_app > ourth_app_$(date +%Y%m%d).dump
```

### Test Restore
```bash
# Monthly restore test
pg_restore -U ourth -d ourth_app_test ourth_app_20240115.dump
```

---

## 🔍 Query Performance Monitoring

### Enable Query Logging

```sql
-- In postgresql.conf
log_statement = 'all'
log_min_duration_statement = 500  -- Log queries > 500ms

-- Or via psql
ALTER SYSTEM SET log_min_duration_statement = 500;
SELECT pg_reload_conf();
```

### Check Slow Queries

```sql
SELECT query, calls, mean_time, max_time
FROM pg_stat_statements
WHERE mean_time > 500
ORDER BY mean_time DESC;
```

### EXPLAIN ANALYZE (Debug Slow Query)

```sql
EXPLAIN ANALYZE
SELECT o.* FROM orders o
WHERE o.vendor_id = 123
AND o.order_status = 'delivered'
AND o.created_at > NOW() - INTERVAL '30 days'
ORDER BY o.created_at DESC;

-- Output shows:
-- - Index used (or sequential scan)
-- - Number of rows
-- - Execution time
-- - Memory usage
```

---

## 🔐 Security Checklist

```
✅ Use strong passwords for DB user
✅ Enable SSL for remote connections
✅ Create read-only user for backups
✅ Encrypt sensitive data in application layer
✅ Never log passwords or card data
✅ Use prepared statements in queries
✅ Regular security audits
```

---

## 🚨 Troubleshooting

### Issue: "Disk space running out"
```sql
-- Check table sizes
SELECT schemaname, tablename, pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename))
FROM pg_tables
WHERE schemaname='public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Solution: Archive old records or partition table
```

### Issue: "Queries suddenly slow"
```sql
-- Run ANALYZE to update statistics
ANALYZE;

-- Identify missing indexes
SELECT schemaname, tablename FROM pg_stat_user_tables
WHERE seq_scan > idx_scan AND seq_scan > 100;
```

### Issue: "Cannot insert duplicate key"
```sql
-- Check for existing records
SELECT * FROM orders WHERE order_number = 'ORD-2024-001';

-- Find duplicate UUIDs
SELECT uuid, COUNT(*) FROM vendors GROUP BY uuid HAVING COUNT(*) > 1;
```

---

## 📚 Files Provided

You have 3 files:

1. **ourth_database_schema.sql**
   - Pure PostgreSQL SQL file
   - Can import directly: `psql ourth_dev < ourth_database_schema.sql`
   - Includes all tables, indexes, and triggers

2. **laravel_migrations.php**
   - Laravel migration files
   - Copy each migration to `database/migrations/`
   - Run with `php artisan migrate`
   - Recommended approach (more Laravel-like)

3. **SCHEMA_DOCUMENTATION.md**
   - Complete documentation
   - Query examples
   - Performance tips
   - Scaling strategy

---

## 🎯 Next Steps

1. ✅ Set up PostgreSQL locally
2. ✅ Create database
3. ✅ Copy migrations to Laravel project
4. ✅ Run `php artisan migrate`
5. ✅ Create Models for each table
6. ✅ Set up Repositories/Services
7. ✅ Build API endpoints
8. ✅ Test with Laravel Sail

---

## 🎓 Learning Resources

### PostgreSQL
- Official Docs: https://www.postgresql.org/docs/
- Performance Tips: https://www.postgresql.org/docs/current/performance-tips.html

### Laravel
- Database: https://laravel.com/docs/database
- Relationships: https://laravel.com/docs/eloquent-relationships
- Query Builder: https://laravel.com/docs/queries

### Best Practices
- Database Design: https://en.wikipedia.org/wiki/Database_design
- Indexing: https://www.postgresql.org/docs/current/indexes.html
- ACID Properties: https://en.wikipedia.org/wiki/ACID

---

**Questions? Stuck somewhere? Check SCHEMA_DOCUMENTATION.md for detailed explanations!**

Last Updated: January 2024
