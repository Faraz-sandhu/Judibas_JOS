# Shared company directory

The directory was cleaned on 6 November 2026 using the owner-confirmed company names.

## Current companies

- Judibas GOC
- Skyinfinit
- Cruxlo
- Wecoma
- Bedizen
- NLTPL
- China bedding store
- Yubis
- Sincwell
- Suivillion
- Corzella
- True North
- Oxyien
- Judibas Store
- Judibas Canteen
- True Water
- Gaming Lounge
- Backend - FSD
- Sales Office - FSD
- Sales Office - MUL

`All` is a filter, never a company record. The shared `communication_companies` directory remains the source of truth. PMS mirrors link back through `platform_company_id`; matching names across products do not create independent companies.

## Cleanup performed

- Consolidated Judibass and Judibas as Judibas GOC, retaining the CV Bank owner and existing career URL.
- Preserved user accounts, all Canteen and CV Bank records, and Accounting journals of retained companies.
- Removed five explicitly labelled test/demo companies and their dependent demonstration records.
- Removed the empty legacy PMS test companies Cedar Retail and Northstar Foods.
- Removed obsolete company assignments from preserved users; did not assign them automatically to another real company.
- Updated legacy column defaults and company IDs held in invitation JSON.
- Verified 20 shared companies and 20 linked PMS company mirrors, no test companies, no orphan user company references, and all Accounting immutability guards enabled.
- Home-page design was not changed.

## Private backup and maintenance script

Before changing data, the cleanup wrote and verified a full database **data snapshot** in `storage/app/private/company-cleanup/`, with table column metadata, sequence values, a completion marker and SHA-256 checksum. This is private application data, not a public download or a schema/migration backup. Existing uploaded files were not deleted. A private JSON cleanup report records removed row counts.

`scripts/normalize-company-directory.php` is a one-time maintenance tool, not a startup migration or application endpoint. Its default mode only prints a plan. `--rehearse` exercises the cleanup inside a transaction and rolls it back; `--apply` writes a verified private snapshot and commits. Do not rerun it after adding companies without reviewing the allowed directory. Unknown non-test names stop the operation.

The maintenance transaction locks tables, preserves FK checks, temporarily defers and then restores original FK characteristics, and restores the four Accounting immutability triggers before commit. Failures roll back all changes. Ordinary application permissions and accounting posting behavior are unchanged.

Demo seeders can recreate fictional companies if explicitly run; do not run sample-data seeders against the cleaned database.
