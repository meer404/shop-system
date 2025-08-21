# Purchase & Sales Manager (Receipts Enabled)

- Every sale now has its **own receipt** page at `sale_receipt.php?id=SALE_ID`.
- After saving a sale, the success message includes a link: **Open receipt**.

## Quick start
1. Create DB, import `schema.sql`.
2. Update `inc/config.php` with DB credentials.
3. Open `/sale_new.php`, create a sale, then click **Open receipt**.
4. Print the receipt (browser print).

## Files
- `sale_receipt.php` – printable receipt with number like `INV-YYYYMMDD-ID`
- `sale_new.php` – links to receipt on success
- Other pages: `index.php`, `customers.php`, `products.php`, `payments.php`, `customer_view.php`


- New: `receipts.php` lists **all receipts** with filters (customer, date range, credit-only) and links to printable pages.
