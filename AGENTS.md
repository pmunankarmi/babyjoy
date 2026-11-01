# WordPress build defaults

- Disable Gutenberg for all post types and use classic widget editing.
- Include a real theme screenshot for Appearance → Themes (1200 × 900 preferred).
- Use ACF Pro for custom fields and theme options, preferring native WordPress fields where suitable.
- Use Polylang's standard translation workflow for pages.
- Reuse the existing guarded polylang-slug.php integration for shared translated slugs; do not load duplicate copies.
- Keep the theme-options logo field and WordPress Customizer custom_logo synchronized in both directions, with one attachment as the source of truth.

BabyJoy exception: WooCommerce products remain single records with Arabic ACF fields; do not add Polylang for WooCommerce.
