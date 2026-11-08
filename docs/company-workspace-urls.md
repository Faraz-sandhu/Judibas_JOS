# Readable company workspace URLs

Company selections use product/company-name paths, for example:

- /pms/skyinfinit
- /pms/all
- /accounting/cruxlo
- /accounting/all
- /canteen/skyinfinit
- /cv-bank/skyinfinit
- /daymark/skyinfinit

The URL resolves to the existing shared company ID. Product permissions and company membership remain mandatory. All in PMS means all permitted companies; Accounting all keeps its existing administrator restriction. Canteen and CV Bank continue to use one company at a time. No products, roles, accounting rules or duplicate companies are created.

CompanyWorkspace builds consistent slugs from the shared directory. Duplicate normalized names and reserved words receive an ID suffix to keep URLs distinct. If a company is renamed, new URLs follow its new name. Numeric query links remain supported and are normalized by the client after loading. No migration is required.

Workspace components use companyWorkspace.ts to resolve company paths, preserve navigation/filters/hash fragments and update browser history when switching companies. APIs continue using validated numeric company IDs. PMS keeps its per-request company header and its existing server-enforced project/task visibility. Company-specific aliases are registered after concrete API, print, receipt, CV and invitation routes.

Accounting combined-report contributors, records and notifications emit readable company links. Public careers URLs remain independent.

Validation: CompanyWorkspaceUrlTest covers all workspace aliases, product/company authorization, guest redirects, reserved API routing, old URLs, conflicting queries and slug collisions. ProductCatalogueTest covers the product card links. Browser scripts check PMS tabs/navigation/export scope and Accounting/CV Bank/Canteen/Daymark rendering and selection.

Catalogue All actions are bounded by config/product-catalogue.php. /pms/all is limited to the PMS card's companies and user assignments. Accounting cards append workspace_group=services, ecommerce or physical; the combined queries and selector use that group. Generic Accounting All uses the union of its configured company groups. Group context persists in navigation and contributor links.
