# Public-site route availability

The public homepage is served by `HomeController@index` and `resources/views/welcome.blade.php`.

The about, contact, careers, privacy, cookie, and course pages do not have corresponding Blade templates, so their routes are not registered. Requests return HTTP 404 without retaining controllers that only check for missing views. When a page is implemented, add its template and a deliberate route together.

The `/blog` and `/search` routes have been removed because no blog or search implementation exists. The search icon and modal are not rendered. Language-specific course URLs that previously reused the generic kids action, as well as course URLs pointing to missing controller methods, are also removed until verified page implementations exist. They must not serve unrelated generic course content.

Navigation no longer links to these unavailable pages. The desktop mega-menu keeps non-functional catalogue labels as plain text rather than `href="#"` links; the responsive menu contains only the working homepage and telephone links. Public enquiry/competition/newsletter/review forms remain governed by their dedicated `/public` routes.

When a page is implemented, add its Blade template, restore a deliberate route and navigation entry, and cover the intended response in feature tests. Do not replace a missing page with invented content or a generic success response.
