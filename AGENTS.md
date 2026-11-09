# Product frontend organisation

- Give every new product a separate folder under `resources/js/modules/<product-slug>/`.
- Keep each product's workspace, product-specific components, styles, types and helpers in its own module; do not place new product implementations directly in `resources/js/`.
- Keep `App.vue` as the integration point, using lazy imports for product workspaces.
- Put genuinely shared UI in `resources/js/components/` (for example `Pagination.vue`), rather than importing components from another product module.
- Preserve the existing shared authentication and product/company permission model when adding products.
