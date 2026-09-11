import { startStimulusApp } from '@symfony/stimulus-bridge';

// Registers Stimulus controllers from controllers.json and in the controllers/ directory
export const app = startStimulusApp(import.meta.webpackContext('@symfony/stimulus-bridge/lazy-controller-loader!./controllers', {
    recursive: true,
    regExp: /\.[jt]sx?$/,
}));
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
