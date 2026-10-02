import { startStimulusApp } from '@symfony/stimulus-bundle';
import PasswordVisibility from '@stimulus-components/password-visibility';

const app = startStimulusApp();
app.register('password-visibility', PasswordVisibility);
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
