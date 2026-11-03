import EditorController from './controllers/editor_controller.js';

/**
 * Registers the controllers of the bundle in a Stimulus application:
 *
 *     import { registerStaticSiteControllers } from '@byfareska/static-site';
 *     registerStaticSiteControllers(app);
 */
export function registerStaticSiteControllers(application) {
    application.register('static-site-editor', EditorController);

    return application;
}

export { EditorController };
