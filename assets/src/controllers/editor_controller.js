// The editor of HTML texts (TinyMCE, GPL licence, self-hosted: nothing from Tiny's cloud).
// Its options come from RichTextType as data-static-site-editor-*-value attributes.
import { Controller } from '@hotwired/stimulus';
import tinymce from 'tinymce/tinymce';
import 'tinymce/models/dom';
import 'tinymce/themes/silver';
import 'tinymce/icons/default';
import 'tinymce/plugins/autolink';
import 'tinymce/plugins/autoresize';
import 'tinymce/plugins/code';
import 'tinymce/plugins/image';
import 'tinymce/plugins/link';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/table';
import 'tinymce/skins/ui/oxide/skin.js';
import 'tinymce/skins/ui/oxide/content.js';
import 'tinymce/skins/content/default/content.js';

export default class extends Controller {
    static values = {
        mode: { type: String, default: 'full' }, // full | inline
        language: { type: String, default: '' },
        placeholders: { type: Array, default: [] }, // [{name, description}]
        blocks: { type: Array, default: [] }, // [{title, html}]
        headings: { type: Array, default: ['h2', 'h3'] },
        files: { type: String, default: '' }, // URL of [{title, value}] for links and images
        contentCss: { type: String, default: '' },
        contentStyle: { type: String, default: '' },
    };

    async connect() {
        const inline = this.modeValue === 'inline';
        // A hidden field with "required" would stop the form; the server checks anyway.
        this.element.removeAttribute('required');
        const blockFormats = ['Akapit=p', ...this.headingsValue.map((h) => `${h === 'h2' ? 'Nagłówek' : 'Podnagłówek'} (${h})=${h}`)];
        const files = this.filesValue;
        const [editor] = await tinymce.init({
            target: this.element,
            license_key: 'gpl',
            language: this.languageValue || undefined,
            skin: 'oxide',
            content_css: this.contentCssValue || 'default',
            content_style: this.contentStyleValue || undefined,
            promotion: false,
            branding: false,
            menubar: false,
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
            convert_urls: false,
            relative_urls: false,
            link_default_target: '',
            link_title: false,
            link_context_toolbar: true,
            link_list: files ? (done) => this.fetchFiles(files, done) : undefined,
            image_list: files ? (done) => this.fetchFiles(files, done, true) : undefined,
            image_caption: true,
            image_dimensions: false,
            table_default_attributes: {},
            table_default_styles: {},
            formats: { accent: { inline: 'span', classes: 'accent' } },
            // What the sanitizer of the server keeps (static_site.content / .inline): itemprop of schema.org, the colour
            // classes of the site.
            extended_valid_elements: 'span[class|itemprop],strong[class],em[class],abbr[title],figure[class],figcaption,'
                + 'blockquote[cite],cite[itemprop],time[datetime],data[value]',
            setup: (ed) => this.setup(ed),
        });
        this.editor = editor;
    }

    setup(ed) {
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
        if (this.blocksValue.length > 0) {
            ed.ui.registry.addMenuButton('siteblocks', {
                text: 'Wstaw blok',
                fetch: (cb) => cb(this.blocksValue.map((block) => ({
                    type: 'menuitem',
                    text: block.title,
                    onAction: () => ed.insertContent(block.html),
                }))),
            });
        }
        if (this.placeholdersValue.length > 0) {
            ed.ui.registry.addMenuButton('placeholders', {
                text: 'Wstaw dane',
                tooltip: 'Dane wstawiane przy publikacji (zmieniają się same)',
                fetch: (cb) => cb(this.placeholdersValue.map((p) => ({
                    type: 'menuitem',
                    text: p.description ? `${p.description}  {${p.name}}` : `{${p.name}}`,
                    onAction: () => ed.insertContent(`{${p.name}}`),
                }))),
            });
        }
        // The <textarea> always holds what is in the editor, whatever sends the form (a live component too).
        ed.on('change input undo redo', () => {
            ed.save();
            this.element.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    async fetchFiles(url, done, imagesOnly = false) {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const files = await response.json();
            done(imagesOnly ? files.filter((f) => /\.(png|jpe?g|gif|webp|svg|avif)$/i.test(f.value)) : files);
        } catch (e) {
            done([]);
        }
    }

    disconnect() {
        this.editor?.remove();
        this.editor = null;
    }
}
