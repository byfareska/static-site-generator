# byfareska/static-site-generator-bundle

A Symfony bundle that renders a site from Twig into static files and publishes it to named targets through
Flysystem (a local directory, FTP, SFTP or any `FilesystemOperator`). It sends only what changed, never deletes files
it did not write (an `.htpasswd` put on the server by hand stays), keeps every build for a rollback and resumes an
upload that was cut off.

Optional parts, used when their package is installed:

- **texts**: a catalog of the texts of the site, edited in the panel, with placeholders (`{email}`);
- **TinyMCE editor** for the HTML of the content (`RichTextType`), sanitized on save;
- **byfareska/crud** integration: publish button, publication history with rollback, text pages, a dashboard widget,
  the submissions of the endpoints;
- **endpoints**: the dynamic bits of a static site (a contact form) served by the same application;
- **symfony/messenger / symfony/scheduler**: publishing in a worker, scheduled content published by itself.

## Install

```bash
composer require byfareska/static-site-generator-bundle league/flysystem-ftp
```

```php
// config/bundles.php
Byfareska\StaticSite\StaticSiteBundle::class => ['all' => true],
```

The bundle maps its entities (`Publication`, `TargetState`, `SiteState`, `SiteText`, `SiteSubmission`): run
`doctrine:migrations:diff` and migrate. Every SQL it writes runs on SQLite, MySQL and PostgreSQL.

```yaml
# config/packages/static_site.yaml
static_site:
    base_url: '%env(SITE_URL)%'           # https://example.com: canonical links, Open Graph, sitemap
    static_dirs: ['%kernel.project_dir%/site/static']   # css/, js/, images, your own .htaccess
    keep_builds: 15
    targets:
        local:     { label: 'Local copy', dsn: 'local://%kernel.project_dir%/var/site' }
        produkcja: { label: 'example.com (FTP)', dsn: '%env(SITE_FTP_DSN)%' }
    publish:
        mode: messenger                    # messenger | terminate | cron (default: messenger when installed)
```

`bin/console config:dump-reference static_site` lists every option.

### Targets

| DSN | |
|---|---|
| `local:///var/www/site` | a directory; `strategy: symlink` makes `releases/<build>` and switches a `current` link atomically |
| `ftp://user:pass@host:21/public_html?passive=1&timeout=30` | `ftps://` for TLS; `ignore_passive_address=1` behind NAT |
| `sftp://user:pass@host:22/var/www?private_key=/path/key` | needs `league/flysystem-sftp-v3` |
| `service: my.filesystem` | any `FilesystemOperator` (flysystem-bundle, S3...) |

The path of an FTP address is in the directory the account logs into (`ftp://host/` is that directory,
`ftp://host/public_html` its subdirectory); an absolute one starts with `%2F` (`ftp://host/%2Fvar/www`), as in RFC 1738.
Keep passwords in `.env.local`.

A target knows what it holds (the manifest of its last build, in `TargetState`), so a publication compares two
manifests and does not list the server. A file deleted on the server by hand comes back only when it changes.

## The site

Pages, files and redirects come from services of the application (autoconfigured by their interface):

```php
use Byfareska\StaticSite\Page\{PageProvider, RenderContext, SitePage, SchedulesChanges};

final readonly class ArticlePages implements PageProvider, SchedulesChanges
{
    public function pages(RenderContext $context): iterable
    {
        yield new SitePage('index.html', 'site/home.html.twig', ['latest' => $this->articles->visible($context, 3)], priority: 1.0);
        foreach ($this->articles->visible($context) as $article) {
            yield new SitePage('artykuly/' . $article->slug . '.html', 'site/article.html.twig', ['article' => $article],
                lastModified: $article->modifiedAt);
        }
    }

    // An article dated ahead: the site changes by itself then (see "Scheduled content").
    public function nextChangeAfter(\DateTimeImmutable $after): ?\DateTimeImmutable { /* ... */ }
}
```

- `RenderContext` says whether a build or the preview of the panel draws the page, and whether drafts are shown
  (`includeDrafts`, the preview "with drafts").
- `FileProvider::files()` yields `SiteFile::copy($path, $source)` or `SiteFile::contents($path, $text)` (uploads,
  generated images).
- `RedirectProvider::redirects()` yields `new Redirect('old.html', 'artykuly/new.html')`: a `301` in `.htaccess`
  (Apache) and a page at the old address that sends on (every other server, `file://`).
- `sitemap.xml` and `robots.txt` are written unless the site has its own.

In the templates:

| | |
|---|---|
| `site_path('css/style.css')`, `site_path('artykuly/')` | a link relative to the page: works on any domain, in a subdirectory, in the preview, from `file://` |
| `site_asset('css/style.css')` | the same with `?v=<hash>` of the file |
| `site_url('artykuly/')` | the absolute address (canonical, Open Graph, feeds) |
| `html\|site_links` | `href="/..."` of HTML written in the panel made relative |
| `site_page`, `site_context` | the page being drawn, its `RenderContext` |

## Texts

The texts of the layout live in a catalog of the application (`TextCatalog`: groups of `TextItem(key, label, type,
default)`); the database keeps only what was changed in the panel. Types: `plain`, `multiline`, `inline` (one line with
bold, italics, links, `span.accent`) and `html`.

```twig
<h1>{{ t_inline('hero.title') }}</h1>
<p>{{ txt('hero.button') }}</p>
<div>{{ t_html('problem.body') }}</div>
{{ article.body|rich }}   {# HTML of an entity: placeholders filled, links relative #}
```

A `PlaceholderProvider` gives the placeholders written in texts and content (`{email}`). `static-site:texts` lists
the stored texts no catalog knows any more.

## Content and the editor

Entities of the site implement the marker `SiteContent`: saving one marks the site "changed" (per target, in the
panel). An HTML property gets `#[SiteHtml(SiteHtml::CONTENT)]` (blocks) or `#[SiteHtml(SiteHtml::INLINE)]`: it is
sanitized on save, and its form field is guessed as the TinyMCE editor. `headingIds: ['h3']` gives headings without an
id one made of their text, so that a link can point at a section.

```yaml
static_site:
    editor:
        language: pl                       # from tinymce-i18n, loaded by your entry point
        content_css: build/site-editor.css # the site's stylesheet in the editor (an asset, versioned by its manifest)
        blocks:                            # the "insert block" menu
            - { title: 'Quote with comment', html: '<figure><figcaption>…</figcaption><blockquote><p>…</p></blockquote></figure>' }
```

The editor is a Stimulus controller of the npm package in `assets/` (`"@byfareska/static-site": "file:vendor/byfareska/static-site-generator-bundle/assets"`,
peer dependency `tinymce`):

```js
import { registerStaticSiteControllers } from '@byfareska/static-site';
registerStaticSiteControllers(app);
import 'tinymce-i18n/langs8/pl.js';
```

## Publishing

`Publisher::request($targets, $user)` queues a publication (or returns the one already running); it builds the site
into `builds_dir/<id>/`, then sends it to each target: files other than HTML first, then the pages, the sitemap, the
deletions last, each written under a temporary name and moved into place. Where it runs is `publish.mode`:

| mode | for | |
|---|---|---|
| `messenger` | Docker, a VPS: a worker | `messenger:consume async`, the message `PublishSite` routed to it |
| `terminate` | shared hosting without a worker | after the response is sent (`kernel.terminate`) |
| `cron` | anything else | `static-site:run-pending` every minute |

From the console, anywhere: `static-site:publish [targets...] [--rollback=<publication>] [--build-only]`.

### Scheduled content

A `PageProvider` or `FileProvider` that implements `SchedulesChanges` tells when its pages change by themselves (an
article dated ahead). The panel shows "scheduled for ...", and:

- with `symfony/scheduler`, a worker consuming `scheduler_static_site` publishes when the date comes
  (`publish.schedule_every`, 5 minutes by default);
- without a worker, cron runs `static-site:publish-scheduled`.

## The panel (byfareska/crud)

```yaml
static_site:
    panel: { section: app.nav.site, texts_section: app.nav.home, role: ROLE_ADMIN }
```

```yaml
# config/routes/static_site.yaml
static_site_panel:
    resource: .
    type: static_site_panel
    prefix: /admin
```

It adds: the button "Publish" with the targets and the state of each (top bar), the resource "Publications" (log,
rollback to a target, retry), the dashboard widget, "Texts" with a page per group of the catalog, the preview
(`/admin/podglad/live/`, `/admin/podglad/drafts/`), and "Submissions" when the site has endpoints.
Translations: Polish and English.

## Endpoints

The dynamic parts of a static site - a contact form, a sign-up - are controllers of the same application:

```php
#[AsSiteEndpoint('contact', '/kontakt', rateLimit: '5/10min')]
final readonly class ContactEndpoint
{
    public function __invoke(Request $request, #[MapRequestPayload] ContactRequest $contact): EndpointResult
    {
        $submission = $this->submissions->record('contact', $request, ['email' => $contact->email, 'message' => $contact->message]);
        $this->bus->dispatch(new ContactReceived($submission->id));   // the e-mail, in the background

        return EndpointResult::done('Thank you, we will write back soon.');
    }
}
```

```yaml
# config/routes/static_site.yaml
static_site_endpoints:
    resource: .
    type: static_site_endpoints
```

```twig
<form method="post" action="{{ site_endpoint('contact') }}" data-site-endpoint="contact">
    <input name="email" type="email"> <textarea name="message"></textarea>
    <button>Send</button>
</form>
{{ site_endpoint_script() }}
```

The script sends the form with `fetch` and shows the errors by the fields (`422` with `{errors: {field: message}}`);
without JavaScript the form posts and comes back with `?wyslano=1` (or `?blad=...`). Protections: `Origin`/`Referer`
of the site, a honeypot field (a bot is answered "sent", its submission kept as spam), a signed token at least
`minSeconds` old, a rate limit per address and a body size limit.

**An endpoint works only where the same application serves the site** - the same server, the same domain, one
database. A target says so with `backend: true`; publishing pages that call an endpoint to a target without it is
refused. There is no API-only mode and no CORS.

### Serving a site with endpoints

Apache (shared hosting): the `.htaccess` of a `backend: true` target routes the prefix to the front controller of the
application:

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^api(/.*)?$ index.php [QSA,L]
```

nginx (Docker, VPS) with a `symlink` target in `/srv/app/var/site`:

```nginx
server {
    root /srv/app/var/site/current;
    index index.html;

    location /api/ {
        root /srv/app/public;
        try_files $uri /index.php$is_args$args;
    }
    location ~ ^/index\.php(/|$) {
        root /srv/app/public;
        fastcgi_pass php:9000;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        internal;
    }
    location / {
        try_files $uri $uri/ $uri.html =404;
    }
}
```

The panel stays on another host name, or under `/admin` with its own `location`.

## Development

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

The functional tests run a small application (`tests/Functional/App`): two local targets, an endpoint, a catalog of
texts.
