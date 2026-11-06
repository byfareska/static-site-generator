/*
 * The editor of the HTML of the site (RichTextType): TinyMCE through the adapter of @byfareska/crud, so images and
 * files are uploaded into the text and picked from the library of the panel. Registered under the name the field
 * gives (data-rich-text-editor-value, "static-site"):
 *
 *     import { registerStaticSiteEditor } from '@byfareska/static-site';
 *     registerStaticSiteEditor(() => import('./tinymce.js').then((module) => module.default));
 *
 * TinyMCE is the application's, with the plugins autolink, autoresize, code, image, link, lists and table.
 *
 * What the field says of the site comes in the options as static_site_* (mode, headings, blocks, placeholders): they
 * make the toolbar, the formats of the blocks and the menus "Wstaw blok" and "Wstaw dane".
 */
import { registerRichTextEditor } from '@byfareska/crud';
import { tinymceEditor } from '@byfareska/crud/editors/tinymce.js';

export const EDITOR = 'static-site';

const SITE_OPTIONS = ['static_site_mode', 'static_site_headings', 'static_site_blocks', 'static_site_placeholders'];

export function staticSiteEditor(tinymce = globalThis.tinymce, defaults = {}) {
    let loaded = null;
    // A function giving TinyMCE is called once, for all the fields.
    const load = typeof tinymce === 'function' ? () => (loaded ??= Promise.resolve(tinymce())) : () => tinymce;

    return {
        async mount(textarea, context) {
            const options = { ...(context.options ?? {}) };
            const site = {
                inline: options.static_site_mode === 'inline',
                headings: options.static_site_headings ?? ['h2', 'h3'],
                blocks: options.static_site_blocks ?? [],
                placeholders: options.static_site_placeholders ?? [],
            };
            for (const name of SITE_OPTIONS) {
                delete options[name];
            }
            const editor = tinymceEditor(await load(), {
                license_key: 'gpl',
                ...defaults,
                ...siteOptions(site),
                setup(ed) {
                    setup(ed, site);
                    defaults.setup?.(ed);
                },
            });

            return editor.mount(textarea, { ...context, options });
        },
    };
}

export function registerStaticSiteEditor(tinymce = globalThis.tinymce, defaults = {}) {
    registerRichTextEditor(EDITOR, staticSiteEditor(tinymce, defaults));
}

function siteOptions({ inline, headings }) {
    const blockFormats = ['Akapit=p', ...headings.map((h) => `${h === 'h2' ? 'Nagłówek' : 'Podnagłówek'} (${h})=${h}`)];

    return {
        statusbar: false,
        plugins: inline ? 'autolink link autoresize code' : 'autolink link lists table image code autoresize',
        toolbar: inline
            ? 'bold italic accent abbr link | placeholders | removeformat code | undo redo'
            : 'blocks | bold italic accent abbr | bullist numlist blockquote | link image table | siteblocks placeholders | removeformat code | undo redo',
        block_formats: blockFormats.join('; '),
        // One line: Enter is a <br>, never a new paragraph.
        newline_behavior: inline ? 'linebreak' : 'default',
        min_height: inline ? 90 : 260,
        autoresize_bottom_margin: 20,
        entity_encoding: 'raw',
        link_default_target: '',
        link_title: false,
        link_context_toolbar: true,
        image_caption: true,
        image_dimensions: false,
        table_default_attributes: {},
        table_default_styles: {},
        formats: { accent: { inline: 'span', classes: 'accent' } },
        // What the sanitizer of the server keeps (static_site.content / .inline): itemprop of schema.org, the colour
        // classes of the site.
        extended_valid_elements: 'span[class|itemprop],strong[class],em[class],abbr[title],figure[class],figcaption,'
            + 'blockquote[cite],cite[itemprop],time[datetime],data[value]',
    };
}

function setup(ed, { blocks, placeholders }) {
    ed.ui.registry.addToggleButton('accent', {
        text: 'Akcent',
        tooltip: 'Wyróżnienie kolorem akcentu',
        onAction: () => ed.formatter.toggle('accent'),
        onSetup: (api) => {
            api.setActive(ed.formatter.match('accent'));
            const handler = ed.formatter.formatChanged('accent', (state) => api.setActive(state));
            return () => handler.unbind();
        },
    });
    ed.ui.registry.addButton('abbr', {
        text: 'Skrót',
        tooltip: 'Skrót z rozwinięciem (<abbr title>)',
        onAction: () => {
            const selected = ed.selection.getContent({ format: 'text' });
            ed.windowManager.open({
                title: 'Skrót',
                body: { type: 'panel', items: [
                    { type: 'input', name: 'text', label: 'Skrót' },
                    { type: 'input', name: 'title', label: 'Rozwinięcie' },
                ] },
                initialData: { text: selected, title: '' },
                buttons: [{ type: 'cancel', text: 'Anuluj' }, { type: 'submit', text: 'Wstaw', primary: true }],
                onSubmit: (api) => {
                    const data = api.getData();
                    if (data.text) {
                        const abbr = ed.dom.create('abbr', { title: data.title }, ed.dom.encode(data.text));
                        ed.selection.setNode(abbr);
                    }
                    api.close();
                },
            });
        },
    });
    if (blocks.length > 0) {
        ed.ui.registry.addMenuButton('siteblocks', {
            text: 'Wstaw blok',
            fetch: (cb) => cb(blocks.map((block) => ({
                type: 'menuitem',
                text: block.title,
                onAction: () => ed.insertContent(block.html),
            }))),
        });
    }
    if (placeholders.length > 0) {
        ed.ui.registry.addMenuButton('placeholders', {
            text: 'Wstaw dane',
            tooltip: 'Dane wstawiane przy publikacji (zmieniają się same)',
            fetch: (cb) => cb(placeholders.map((p) => ({
                type: 'menuitem',
                text: p.description ? `${p.description}  {${p.name}}` : `{${p.name}}`,
                onAction: () => ed.insertContent(`{${p.name}}`),
            }))),
        });
    }
}
