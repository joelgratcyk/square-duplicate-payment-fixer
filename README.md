# Square Duplicate Payment Method Fixer

**A one-time cleanup tool for duplicate Square payment methods in Event Espresso (Multisite compatible)**

## IMPORTANT DISCLAIMER

**THIS PLUGIN IS FOR ONE-TIME USE ONLY.**  
It is designed to be activated, run once, and then **DELETED**. Do not keep it active on your site. Use at your own risk. 

## Description

This plugin helps resolve an issue where duplicate Square payment method records appear in Event Espresso, causing both "Connect" and "Connected" buttons to display simultaneously along with potential critical errors.

It safely:
- Identifies all Square payment methods in your database
- Preserves one working method
- Removes duplicate records
- Cleans up associated metadata

## Features

- **Multisite compatible** - Install network-wide, activate on individual sites only
- **Safe one-time execution** - Won't run twice on the same site
- **No data loss** - Preserves one working payment method
- **Detailed reporting** - Shows exactly what was found and fixed
- **Security built-in** - Nonce verification and admin-only access
- **Reminder system** - Nags you to delete it when done

## Requirements

- WordPress 5.0+
- PHP 7.2+
- Event Espresso 4.0+ with Square Payment Method add-on installed

## Installation

### For Multisite Networks (Your Setup)

1. **Upload to Network**  
   - Go to **Network Admin → Plugins → Add New**
   - Upload the plugin ZIP file
   - Click "Install Now"

2. **Activate on specific subsite only**  
   - Navigate to your target subsite's admin dashboard
   - Go to **Plugins → Installed Plugins**
   - Find "Square Payment Method Fixer"
   - Click "Activate" (DO NOT network activate)

3. **Run the cleanup**  
   - After activation, click the "Run Cleanup" link on the plugins page
   - Or visit any admin page with `?square_fixer_run=1` parameter
   - Results will display immediately

4. **Delete the plugin**  
   - Deactivate it first
   - Then delete it (a persistent reminder will appear until you do)

### For Single Site Installations

1. Upload and activate normally
2. Run cleanup via the "Run Cleanup" link
3. Deactivate and delete when done

## What It Fixes

This plugin addresses the symptom where you see:
- Both Square Connect and Connected buttons simultaneously
- Duplicate Square settings widgets
- Database errors related to payment methods
- Critical errors in dashboard

## Technical Details

The plugin performs these database operations:

```sql
-- Finds all Square payment methods
SELECT * FROM wp_esp_payment_method WHERE PMD_slug LIKE '%square%'

-- Deletes duplicates (keeps first one)
DELETE FROM wp_esp_payment_method WHERE PMD_ID = [duplicate_id]

-- Cleans up associated extra meta
DELETE FROM wp_esp_extra_meta WHERE EXM_type = 'PaymentMethod' AND EXM_ID = [duplicate_id]

-- Removes leftover options
DELETE FROM wp_options WHERE option_name = 'ee_payment_method_squareonsite_settings'
```

For multisite, the plugin automatically uses the correct table prefix (e.g., wp_2_ for site ID 2).

## Security
Admin-only execution (manage_options capability required)

Nonce verification for all actions

No sensitive data stored

Self-deleting reminder system

## Frequently Asked Questions

**Q: Will this delete my Square account connection?**  
**A:** No. It only removes duplicate database records. You may need to reconnect Square on the preserved method.

**Q: Can I run this on multiple sites?**  
**A:** Yes! Install network-wide, then activate and run on each subsite individually.

**Q: What if I have more than one Square payment method intentionally?**  
**A:** This plugin assumes duplicates are unintended. If you purposely have multiple Square methods, DO NOT use this plugin.

**Q: Will this affect my transaction history?**  
**A:** No. Payment records are stored separately and are not affected.
