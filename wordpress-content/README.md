# BabyJoy uploaded media

Copy `uploads/babyjoy/` into the site's `wp-content/uploads/babyjoy/` before installing the updated theme. Existing WordPress attachment IDs and country retailer logos remain in the database and standard uploads directory. The theme screenshot stays in the theme for Appearance → Themes.

`python3 scripts/package.py` creates `babyjoy-media.zip` with paths relative to `wp-content`; extract it there. Deploying the theme ZIP alone does not deploy uploaded media or country page records.

Stylesheet source remains in the theme. WordPress generates cached stylesheets under uploads/babyjoy/styles with media URLs pointing into uploads; content hashes refresh the generated files after theme changes or site URL changes.

For an existing migrated BabyJoy site, after deploying theme/core and media, run `wp eval-file scripts/setup-country-pages.php` from the repository and then `wp rewrite flush`. This creates missing linked English/Arabic country homepages and seeds their retailer settings once, preserving existing content and subsequent country edits. The script reads build/country-retailers.json, captured from the supplied staging references. Back up the site's database before migration.
