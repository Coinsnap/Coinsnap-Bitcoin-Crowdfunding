# Maintenance Log — Coinsnap Bitcoin Crowdfunding

## Monthly Review Process

Each month a maintainer should:

1. Check the current stable WordPress version at https://wordpress.org/download/releases/
2. Update `Tested up to` in `readme.txt`, `README.md`, and the plugin header in `coinsnap-bitcoin-crowdfunding.php` if a new WP version was released.
3. Run a quick functional smoke test:
   - Activate plugin on a staging site running the latest WP.
   - Create a crowdfunding campaign, embed via shortcode.
   - Complete a test donation via Coinsnap (use test credentials).
   - Verify webhook fires and donation status updates to Settled.
   - Check Donors list, Transactions page, and Logs for errors.
4. Only create a new plugin release if there is an actual code change (bug fix, security patch, compatibility fix, or meaningful metadata update). Do **not** bump version solely to appear recently updated.
5. Log the review below (date · reviewer · WP version tested · result).

---

## Review Log

| Date | Reviewer | WP Version Tested | Result | Notes |
|------|----------|-------------------|--------|-------|
| 2026-07-04 | Daniel | 7.0 | ✅ Compatible | Initial compatibility check. Tested up to updated in README.md. readme.txt and plugin header were already at 7.0. Branding assets (.wordpress-org) already complete. |
