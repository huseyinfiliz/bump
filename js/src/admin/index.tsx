import app from 'flarum/admin/app';
import BumpSettingsPage from './BumpSettingsPage';

app.initializers.add('huseyinfiliz/bump', () => {
  app.extensionData.for('huseyinfiliz-bump').registerPage(BumpSettingsPage);
});
