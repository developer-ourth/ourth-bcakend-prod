# Zoho Inventory integration

Zoho Inventory is the **source of truth for stock**. The website follows it, and every **paid** website order is created in Zoho with its invoice and payment.

| Direction | What | When |
|---|---|---|
| Zoho → website | Stock for every product/pack whose **SKU** matches a Zoho item | Every 10 min (`zoho sync-stock`), or "Sync stock now" in the admin |
| Website → Zoho | Customer → confirmed **Sales Order** → **Invoice** (marked sent) → **Customer Payment** (Razorpay id as reference) | As soon as the payment is confirmed; anything missed is retried hourly (`zoho push-pending`) |

**Website available stock** = Zoho *available for sale* − quantities in website orders that aren't in Zoho yet (unpaid or still syncing, last 72 h). A customer who is still paying therefore can't be oversold.

## One-time setup (about 10 minutes)

1. Sign in to **https://api-console.zoho.in** with a Zoho Inventory **admin** account, then choose **Add Client → Self Client → Create**. Copy the **Client ID** and **Client Secret**.
2. Open the **Generate Code** tab:
   - **Scope** (paste exactly):
     ```
     ZohoInventory.items.READ,ZohoInventory.contacts.CREATE,ZohoInventory.contacts.READ,ZohoInventory.salesorders.CREATE,ZohoInventory.salesorders.READ,ZohoInventory.invoices.CREATE,ZohoInventory.invoices.READ,ZohoInventory.customerpayments.CREATE,ZohoInventory.settings.READ
     ```
   - **Time duration:** 10 minutes. **Description:** "OURTH website".
   - Click **Create**, choose the OURTH organisation, and copy the code.
3. **Within those 10 minutes**, open the Laravel Cloud console (or a local checkout with the same client id and secret) and run:
   ```
   ZOHO_CLIENT_ID=... ZOHO_CLIENT_SECRET=... php artisan zoho connect <code>
   ```
   It prints `ZOHO_REFRESH_TOKEN=...`.
4. In **Laravel Cloud → Environment variables**, add:
   ```
   ZOHO_CLIENT_ID=...
   ZOHO_CLIENT_SECRET=...
   ZOHO_REFRESH_TOKEN=...
   ZOHO_ORGANIZATION_ID=60094465589
   ZOHO_ORDERS_FROM=<go-live date, e.g. 2026-11-10>   # older orders were entered in Zoho by hand
   ```
   Then deploy. The queue worker and scheduler must be running; both already are for the CRM.
5. Check the connection: run `php artisan zoho check`. It lists five items and the stock fields Zoho returns.
6. Run `php artisan zoho sync-stock` once. Then open **Admin → Zoho Inventory** on the website and fix every SKU listed under "SKUs not found in Zoho".
7. Place a ₹1 test order. In Zoho you should see a confirmed sales order, an invoice and a payment, all with the website order number as the reference. **Check the GST on the invoice:** CGST+SGST for an in-state address, IGST for out-of-state.

## Settings

| Variable | Default | Meaning |
|---|---|---|
| `ZOHO_STOCK_SYNC_MINUTES` | 10 | How often stock is pulled. Each run costs about 1 API call per 200 items. |
| `ZOHO_STOCK_FIELD` | auto | Which Zoho stock number to use. By default it's the first of `available_for_sale_stock`, `actual_available_stock`, `available_stock`, `stock_on_hand` that exists. |
| `ZOHO_PRICES_INCLUDE_TAX` | true | Website prices include GST |
| `ZOHO_PAYMENT_MODE` | others | Zoho payment mode recorded for Razorpay payments |
| `ZOHO_PAYMENT_ACCOUNT_ID` | — | Zoho "Deposit to" account for those payments |
| `ZOHO_ORDERS_FROM` | — | Only orders placed on or after this date go to Zoho |

## Commands

```
php artisan zoho check                 # test connection, show stock fields
php artisan zoho sync-stock            # pull stock now, list SKUs missing in Zoho
php artisan zoho push-order ORD-XXXX   # send one paid order now (shows any warning)
php artisan zoho push-pending          # queue every paid order not yet in Zoho
```

## Known limits

- **Cash-on-delivery orders are not sent**, because only paid orders are (by decision). Enter COD orders in Zoho by hand, or ask for COD support to be added.
- **Cancellations and refunds of paid orders are not pushed.** Void the sales order or raise a credit note in Zoho.
- **API budget.** Zoho allows 100 calls/min and a daily limit by plan (Standard 2,000, Professional 5,000). Stock sync at 10 minutes with up to 200 items uses about 150 calls/day, and each order uses about 8–10 calls.
- **Invoice totals.** If the invoice total differs from the amount paid by ₹1 or more, the order gets a warning in the admin page. That usually means a tax or shipping setting in Zoho.
