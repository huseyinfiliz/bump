import app from 'flarum/admin/app';
import BumpSettingsPage from './BumpSettingsPage';

app.initializers.add('huseyinfiliz/bump', () => {
  app.registry.for('huseyinfiliz-bump').registerPage(BumpSettingsPage);
});
