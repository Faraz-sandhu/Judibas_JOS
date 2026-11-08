# Products page themes

Super Admin: Administration > Settings > Products page.

1. Workspace Grid: Universal Products on the left, company workspace cards on the right.
2. Product Directory: Universal Products across the top, company workspace cards below.
3. Classic: existing Business apps catalogue, preserved without layout changes.
4. Expandable Cards: cards initially show only their icon and name. Click or press Enter/Space on a card to reveal its applications or companies. Only one card can be open at a time; opening another closes the previous card. Search automatically opens the first matching card; clearing search returns the cards to their collapsed state.

The selection is stored globally in site_content (products_theme). Existing installations default to Classic until a new theme is saved. This setting affects /products only; it does not replace the website home page or individual product workspaces. No migration is required.

Company groups are defined in config/product-catalogue.php from the approved diagram. Existing company IDs are resolved by name; no duplicate company records are created. Actual product permissions and company memberships filter employee cards. Public visitors receive no company directory. Unbuilt applications and company workspaces are labelled Coming soon and have no active link. Accounting company buttons open the selected accounting company; PMS company buttons open readable paths such as /pms/skyinfinit; All companies opens /pms/all and includes only Skyinfinit, Cruxlo and Wecoma, intersected with the user's assigned companies. Accounting card links carry workspace_group=services, ecommerce or physical. Each All view, its company selector, records, contributor links and combined totals stay inside that card's configured company list. Generic /accounting/all uses the union of the Accounting cards. The company remains selected through sidebar navigation, full-page links, exports, API requests and refresh. Dashboard/projects/tasks/subtasks/sprints/clients/work logs use the server-enforced company scope. The header names the company and links back to Products to change it. Selection is isolated per browser tab, not stored in the shared session. PMS view-all permissions apply within assigned companies; they do not grant access to unassigned companies. Employee task assignment and team visibility rules continue to apply. Empty company groups and products without access are omitted for employees.

Theme saving uses the existing Super Admin restriction for shared settings. This is presentation configuration and does not grant product or company access.

Validation: ProductCatalogueTest covers persistence, invalid values, authorization, employee company/product filtering and guest privacy. scripts/check-product-themes.mjs checks production layouts across light/dark appearances and mobile/tablet/desktop widths, plus setting tabs and saving/reloading through mocked UI APIs.

PmsCompanyContextTest covers selected/all scopes, forbidden company IDs, task mutations, report totals, work-log CSVs, and normal employee assignment visibility. scripts/check-pms-company-context.mjs checks two browser tabs, headers, navigation, reload and export links against isolated API fixtures.
