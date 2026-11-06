# byfareska/static-site-generator-bundle

A Symfony bundle that renders a site from Twig into static files and publishes it to named targets through
Flysystem (a local directory, FTP, SFTP or any `FilesystemOperator`). It sends only what changed, never deletes files
it did not write (an `.htpasswd` put on the server by hand stays), keeps every build for a rollback and resumes an
upload that was cut off.

Optional parts, used when their package is installed:

- **texts**: a catalog of the texts of the site, edited in the panel, with placeholders (`{email}`);
- **TinyMCE editor** for the HTML of the content (`RichTextType`, the rich text of byfareska/crud), sanitized on save;
- **byfareska/crud** integration: publish button, publication history with rollback, text pages, a dashboard widget,
  the submissions of the endpoints, files uploaded in the panel published with the site;
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
    clean_urls: true                       # /artykuly/x instead of /artykuly/x.html (see "Clean URLs and Markdown")
    markdown: { enabled: true }            # x.md next to every page, served to "Accept: text/markdown"
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
| `html\|site_links` | `href="/..."` of HTML written in the panel made relative; its uploaded files published |
| `site_file(article.cover)` | a link to an uploaded file (`public://ab/cd/x.jpg`, see "Files"), or to a path of the site |
| `site_file_url(article.cover)` | the same as an absolute address (Open Graph) |
| `site_page`, `site_context` | the page being drawn, its `RenderContext` |

## Clean URLs and Markdown

AI agents and tools (Claude Code, Cursor, readers of LLMs) prefer Markdown to HTML: fewer tokens, no layout. With
both options the same address (`/artykuly/x`) serves the HTML to a browser and the Markdown to a client that sends
`Accept: text/markdown`.

```yaml
static_site:
    clean_urls: true            # default false
    markdown:
        enabled: true           # default false; needs clean_urls
        selector: 'main'        # what of the HTML goes into the .md (the <body> when nothing matches)
        exclude: ['nav', 'header', 'footer', 'form', 'script', 'style', 'noscript', 'template', '[data-markdown-skip]']
        llms_txt: true          # a list of the pages for agents
```

**`clean_urls`** writes the links, the canonical addresses, the sitemap and `site_url()` without `.html`
(`artykuly/x.html` is `/artykuly/x`, `artykuly/index.html` is `/artykuly/`). The files keep `.html`; the server maps
the address to the file, and `/x.html` is moved to `/x` for good. A site opened from `file://` has no working links
then.

**`markdown`** writes `x.md` next to every HTML page (`index.md` for a directory), with
`<link rel="alternate" type="text/markdown" href="x.md">` in its `<head>`:

```markdown
---
title: "The <title> of the page"
description: "Its meta description"
url: https://example.com/artykuly/x
updated: 2026-11-01
---
[Strona główna](https://example.com/) · [Spis stron](https://example.com/llms.txt)

# The content of <main>, with absolute links
```

- `new SitePage(..., markdown: false)`: no Markdown version (a form, a 404 page).
- `new SitePage(..., markdown: 'site/article.md.twig')`: the content is drawn by that template, with the variables and
  the links of the page, where the conversion of the HTML is poor. Twig escapes `.md.twig` as HTML: write
  `{% autoescape false %}...{% endautoescape %}` in it.
- `llms.txt` ([llmstxt.org](https://llmstxt.org)): the `<title>` and the meta description of the home page, then
  `- [title](https://example.com/x.md): description` of every page with a Markdown version in the sitemap. Not
  written when the site has its own.
- The preview of the panel serves both: `x` is the page, `x.md` (or `x` with `Accept: text/markdown`) its Markdown.

Apache: the build writes the rules into `.htaccess` (with `RewriteBase` of `base_url`); it needs `mod_rewrite`,
`AllowOverride All` (the block sets `Options -MultiViews`) and, for `Vary: Accept`, `mod_headers`:

```apache
Options -MultiViews
AddType text/markdown .md
AddCharset UTF-8 .md
<IfModule mod_headers.c>
    <FilesMatch "\.(html|md)$">
        Header always merge Vary Accept
    </FilesMatch>
</IfModule>
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
# ... the endpoints and the redirects (both /old.html and /old) ...
# /x.html -> /x, /a/index.html -> /a/ (the requests of the client only, not the rewrites below)
RewriteCond %{THE_REQUEST} \s/+((?:[^?\s]*/)?)index\.html[\s?]
RewriteRule ^ /%1 [R=301,L,NE]
RewriteCond %{THE_REQUEST} \s/+([^?\s]+)\.html[\s?]
RewriteRule ^ /%1 [R=301,L,NE]
# Markdown to a client that wants it (not to a browser, not with q=0)
RewriteCond %{HTTP_ACCEPT} text/markdown [NC]
RewriteCond %{HTTP_ACCEPT} !^\s*text/html [NC]
RewriteCond %{HTTP_ACCEPT} !text/markdown\s*;\s*q=0(\.0*)?\s*(,|$) [NC]
RewriteCond %{REQUEST_FILENAME}index.md -f
RewriteRule ^(.*/)?$ $1index.md [END]
RewriteCond %{HTTP_ACCEPT} text/markdown [NC]
RewriteCond %{HTTP_ACCEPT} !^\s*text/html [NC]
RewriteCond %{HTTP_ACCEPT} !text/markdown\s*;\s*q=0(\.0*)?\s*(,|$) [NC]
RewriteCond %{REQUEST_FILENAME}.md -f
RewriteRule ^((?:.*/)?[^/.]+)$ $1.md [END]
# The HTML by default, the Markdown of a page without one
RewriteCond %{REQUEST_FILENAME}.html -f
RewriteRule ^((?:.*/)?[^/.]+)$ $1.html [END]
RewriteCond %{REQUEST_FILENAME}.md -f
RewriteRule ^((?:.*/)?[^/.]+)$ $1.md [END]
</IfModule>
```

A client gets the Markdown when its `Accept` names `text/markdown` (not with `q=0`) and does not begin with
`text/html`, as the one of a browser does: `text/markdown, text/html;q=0.9, */*` is an agent.

nginx (in the `http {}` that includes `mime.types`):

```nginx
types { text/markdown md; }    # nginx's mime.types has no .md

map $http_accept $site_ext {
    default                                     .html;
    "~*^\s*text/html"                           .html;
    "~*text/markdown\s*;\s*q=0(\.0*)?\s*(,|$)"  .html;
    "~*text/markdown"                           .md;
}

server {
    root /srv/app/var/site/current;
    index index.html;
    charset utf-8;
    charset_types text/markdown text/plain text/css application/javascript application/xml;

    # 301: /x.html -> /x, /a/index.html -> /a/
    if ($request_uri ~ "^(?<p>[^?]*/)index\.html(?<q>\?.*)?$") { return 301 $p$q; }
    if ($request_uri ~ "^(?<p>[^?]*)\.html(?<q>\?.*)?$")       { return 301 $p$q; }

    location / {
        add_header Vary Accept always;
        try_files $uri $uri$site_ext ${uri}index$site_ext $uri.html ${uri}index.html $uri.md $uri/ =404;
    }
    # location /api/ ... as in "Serving a site with endpoints"
}
```

The redirects of a `RedirectProvider` are pages that send on there; write them as `return 301` for a status.

## Texts

The texts of the layout live in a catalog of the application (`TextCatalog`: groups of `TextItem(key, label, type,
default)`); the database keeps only what was changed in the panel. Types: `plain`, `multiline`, `inline` (one line with
bold, italics, links, `span.accent`), `html`, `image` and `file` (a field of files of crud; an empty one is the default
again, which may be a path of the site: `img/hero.jpg`).

```twig
<h1>{{ t_inline('hero.title') }}</h1>
<p>{{ txt('hero.button') }}</p>
<div>{{ t_html('problem.body') }}</div>
<img src="{{ t_file('hero.image') }}" alt="">  {# t_file_url() for Open Graph #}
{{ article.body|rich }}   {# HTML of an entity: placeholders filled, links relative #}
```

A `PlaceholderProvider` gives the placeholders written in texts and content (`{email}`). `static-site:texts` lists
the stored texts no catalog knows any more.

## Content and the editor

Entities of the site implement the marker `SiteContent`: saving one marks the site "changed" (per target, in the
panel). An HTML property gets `#[SiteHtml(SiteHtml::CONTENT)]` (blocks) or `#[SiteHtml(SiteHtml::INLINE)]`: it is
sanitized on save, and with byfareska/crud its form field is guessed as the editor. `headingIds: ['h3']` gives headings
without an id one made of their text, so that a link can point at a section.

The editor (`RichTextType`) is the rich text of byfareska/crud (`CrudRichTextType`) with an editor of its own,
`static-site`: TinyMCE with the accent, abbreviations, "insert block" and "insert data" (the placeholders). With
`static_site.files` its images and files are uploaded into a storage the site publishes and picked from the library of
the panel (`'files' => false` on a field: none). Without crud a `#[SiteHtml]` field is a textarea.

```yaml
static_site:
    editor:
        language: pl                       # from tinymce-i18n, loaded by your entry point (default: <html lang>)
        content_css: build/site-editor.css # the site's stylesheet in the editor (an asset, versioned by its manifest)
        blocks:                            # the "insert block" menu
            - { title: 'Quote with comment', html: '<figure><figcaption>…</figcaption><blockquote><p>…</p></blockquote></figure>' }
```

The editor is in the npm package in `assets/` (`"@byfareska/static-site": "file:vendor/byfareska/static-site-generator-bundle/assets"`,
peer dependencies `@byfareska/crud` and `tinymce`). TinyMCE is the application's, with the plugins autolink,
autoresize, code, image, link, lists and table (see "Rich text" in the README of byfareska/crud):

```js
import { registerStaticSiteEditor } from '@byfareska/static-site';
registerStaticSiteEditor(() => import('./tinymce.js').then((module) => module.default));
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
(`/admin/podglad/live/`, `/admin/podglad/drafts/`), "Submissions" when the site has endpoints, and "Site settings"
(`/admin/ustawienia-strony`) when it publishes uploaded files (see "Files").
Translations: Polish and English.

## Files

Files uploaded in the panel of byfareska/crud (`byfareska_crud.files`) are published with the site: by default every
file of the storages of `static_site.files.storages`, or - the checkbox "Publish every file of the library" of "Site
settings" in the panel unticked - only those its pages use: a field of files shown with `site_file()`, a text of the
type `image` / `file` (`t_file()`), an image or a link of the HTML of the editor (`t_html`, `|rich`, `|site_links`); a
file uploaded and never shown, or shown only by a draft, then stays in the panel.

```yaml
# config/packages/byfareska_crud.yaml
byfareska_crud:
    files:
        storages:
            public: { service: app.public_storage, public_url: /uploads }

# config/packages/static_site.yaml
static_site:
    files:
        storages: { public: uploads }    # public://ab/cd/x.jpg is uploads/ab/cd/x.jpg of the site
        publish_all: true                # the default of the checkbox of the panel (kept in static_site_state)
        editor: true                     # the editor uploads into the first of them; false, or {storage, accept, max_size}
        # a storage the checkbox does not change: storages: { public: uploads, docs: { dir: pliki, publish_all: false } }
```

Ticking or unticking the checkbox is a change of the site: the targets show it as unpublished. The list of every file is
the library of the storage (`byfareska_crud.files.storages.<name>.library`): the table `crud_file` of the library
`doctrine`, the storage listed for `storage` and `none`. A file uploaded less than `byfareska_crud.files.prune_after`
ago that no record uses is left out - the upload of a form not saved yet - as is a file of the library whose bytes
cannot be read (noted in the log). A file deleted from the library is deleted from the targets at the next publication,
like any file the bundle wrote. Each build copies every file of the storages into its directory; the targets get only
those that changed.

```php
#[ORM\Column(nullable: true)]
#[CrudFile(accept: ['images'])]
public ?string $cover = null;           // "public://ab/cd/....jpg"
```

```twig
<img src="{{ site_file(article.cover) }}" alt="">
<meta property="og:image" content="{{ site_file_url(article.cover) }}">
```

The HTML of the editor keeps the addresses of the storage (`/uploads/ab/cd/x.jpg`, of the panel's domain): the build
writes them as paths of the site, relative to the page. The preview serves the files at their paths. The files of the
texts are told to crud's index of usages (resource `static_site_text`), so `byfareska:crud:files:prune` keeps them.

Another source of files the pages use (a DAM, generated images) is a `SiteFileResolver`: a reference or an address to
a `SiteFile` (`SiteFile::stream($path, fn () => $stream)`), and a path back to it for the preview. A source whose files
are all published, used or not, is a `SiteFileLibrary` (`allFiles()`).

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
texts, files of a test resolver; its environment `crud` has the panel of byfareska/crud with a storage of files. They
need the PHP extensions `pdo_sqlite`, `ftp` and `intl`.
