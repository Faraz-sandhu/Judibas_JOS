# Company-specific CV Bank

The shared Judibas CV Bank layout now uses a company selector. Its departments, jobs, applications, theme and page content follow the selected company. Super Admin and platform administrators can manage all companies; regular recruitment/HR staff require CV Bank product access and assigned companies, configured in Administration -> Users or Invitations. HR staff can manage recruitment and career content within those companies. Only Super Admin can create/change public career URLs and publication status.

## Setup

1. Create the company in the shared Super Admin Companies directory if it does not exist.
2. Open CV Bank -> Companies -> Set up careers. Choose a unique slug such as cruxlo, skyinfinit or suivilion and publish the page.
3. Select that company and create its departments.
4. Create/edit jobs with locations (one city per line), employment type, work mode, experience, overview, responsibilities, requirements and optional deadline. On-site and hybrid roles require a location; remote roles may omit it.
5. Configure the selected company's career theme/header/footer/content through Settings. The existing six themes and automatic system colour scheme remain available.
6. Assign CV Bank access and company memberships to HR through the shared Administration dashboard.

Public links use /careers/{company-slug}. Each role has /careers/{company-slug}/jobs/{id}. View details opens the role; Apply opens the company application form with that role and department selected. Applications can include a preferred location from that role's locations. General department applications remain available when there are no jobs.

## Existing data and access

The migration preserves existing IDs, CV paths and content under the Judibas company. /careers remains a compatible Judibas entry point; /careers/judibas is its company URL. Other companies are not automatically published. Duplicate department names are permitted across companies. Foreign keys enforce company ownership for departments, jobs and applications. Department renames update the company's jobs while preserving the application department snapshot. Jobs with existing applications cannot move departments; create another job instead.

Archived jobs, inactive departments, expired deadlines and unpublished company pages do not accept new role applications. Deadlines use Pakistan dates. Unpublished themes can be previewed by authorized company staff. Private CV preview/download and all mutation routes recheck product/company access. Removing access revokes those routes. No applicant data is exposed in public career pages.

Career editor lists are company-specific designated contacts; company/product access remains controlled in Administration. Company settings do not overwrite another company's theme or branding. Changing a career slug changes its public URL, so existing shared links must be updated.

## Verification

- php artisan test --filter=CvBank
- npm run build
- php scripts/render-cv-bank-check-fixtures.php
- node scripts/check-cv-bank-companies.mjs

The browser fixture renderer rolls back its database writes. Browser APIs are mocked; generated public HTML uses the actual Blade themes and job details view. Feature tests cover writes, public application uploads, company isolation, CV authorization, deadlines, settings and database ownership constraints.
