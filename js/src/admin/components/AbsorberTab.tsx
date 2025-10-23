import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Group from 'flarum/common/models/Group';
import SelectGroupsModal from './SelectGroupsModal';

interface AbsorberTabAttrs extends ComponentAttrs {
  buildSettingComponent: (options: any) => any;
  submitButton: () => any;
  setting: (key: string, value?: string) => any;
}

export default class AbsorberTab extends Component<AbsorberTabAttrs> {
  // === Absorber Bypass Groups Methods ===
  getAbsorberBypassGroups(): Group[] {
    const settingValue = this.attrs.setting('huseyinfiliz-bump.absorber-bypass-groups')();
    let selectedGroupIds: string[] = [];

    try {
      selectedGroupIds = JSON.parse(settingValue || '[]');
    } catch (e) {
      console.error('Failed to parse absorber-bypass-groups setting:', e);
    }

    const allGroups = app.store.all<Group>('groups');
    return allGroups.filter((group) => selectedGroupIds.includes(String(group.id())));
  }

  openAbsorberBypassGroupsModal() {
    const settingValue = this.attrs.setting('huseyinfiliz-bump.absorber-bypass-groups')();
    let selectedGroupIds: string[] = [];

    try {
      selectedGroupIds = JSON.parse(settingValue || '[]');
    } catch (e) {
      console.error('Failed to parse absorber-bypass-groups setting:', e);
    }

    app.modal.show(SelectGroupsModal, {
      selectedGroupIds,
      onsubmit: (newSelectedGroupIds: string[]) => {
        this.attrs.setting('huseyinfiliz-bump.absorber-bypass-groups')(JSON.stringify(newSelectedGroupIds));
        m.redraw();
      },
    });
  }

  removeAbsorberBypassGroup(groupId: string) {
    const settingValue = this.attrs.setting('huseyinfiliz-bump.absorber-bypass-groups')();
    let selectedGroupIds: string[] = [];

    try {
      selectedGroupIds = JSON.parse(settingValue || '[]');
    } catch (e) {
      console.error('Failed to parse absorber-bypass-groups setting:', e);
    }

    const newSelectedGroupIds = selectedGroupIds.filter((id) => id !== groupId);
    this.attrs.setting('huseyinfiliz-bump.absorber-bypass-groups')(JSON.stringify(newSelectedGroupIds));
    m.redraw();
  }

  // === Render Badge Helper ===
  renderGroupBadges(groups: Group[], removeCallback: (groupId: string) => void) {
    return groups.length > 0 ? (
      groups.map((group) => {
        const icon = group.icon();
        const color = group.color();

        return (
          <span className="Badge SelectGroups-badge" key={group.id()} style={{ backgroundColor: color || undefined }} title={group.namePlural()}>
            {icon && <i className={'icon ' + icon}></i>}
            {group.namePlural()}
            <button
              className="SelectGroups-badge-remove"
              onclick={(e: Event) => {
                e.preventDefault();
                removeCallback(String(group.id()));
              }}
              type="button"
            >
              &times;
            </button>
          </span>
        );
      })
    ) : (
      <p className="SelectGroups-empty">{app.translator.trans('huseyinfiliz-bump.admin.select_groups_modal.no_groups_selected')}</p>
    );
  }

  view() {
    const { buildSettingComponent, submitButton } = this.attrs;

    return (
      <div className="AbsorberTab">
        {/* Absorber Settings */}
        <div className="SettingsSection">
          <h3>
            <i className="fas fa-hourglass-half"></i>
            {app.translator.trans('huseyinfiliz-bump.admin.tabs.absorber')}
          </h3>

          <div className="SettingsSection-content">
            <div className="Form-group">
              {buildSettingComponent({
                type: 'boolean',
                setting: 'huseyinfiliz-bump.enable-absorber',
                label: app.translator.trans('huseyinfiliz-bump.admin.enable_absorber_label'),
                help: app.translator.trans('huseyinfiliz-bump.admin.enable_absorber_help'),
              })}
            </div>

            <div className="Form-group">
              {buildSettingComponent({
                type: 'number',
                setting: 'huseyinfiliz-bump.threshold-hours',
                label: app.translator.trans('huseyinfiliz-bump.admin.threshold_hours_label'),
                help: app.translator.trans('huseyinfiliz-bump.admin.threshold_hours_help'),
                placeholder: '0',
                min: 0,
              })}
            </div>

            <div className="Form-group">
              <label>{app.translator.trans('huseyinfiliz-bump.admin.absorber_tags_label')}</label>
              {buildSettingComponent({
                type: 'flarum-tags.select-tags',
                setting: 'huseyinfiliz-bump.absorber-tags',
                options: {
                  requireParentTag: false,
                  limits: {
                    max: {
                      secondary: 0,
                    },
                  },
                },
              })}
              <p className="helpText">{app.translator.trans('huseyinfiliz-bump.admin.absorber_tags_help')}</p>
            </div>

            {/* Absorber Bypass Groups */}
            <div className="Form-group">
              <label>{app.translator.trans('huseyinfiliz-bump.admin.absorber_bypass_groups_label')}</label>

              <div className="SelectGroups-container">
                <Button className="Button" onclick={this.openAbsorberBypassGroupsModal.bind(this)}>
                  <i className="fas fa-users"></i>
                  {app.translator.trans('huseyinfiliz-bump.admin.select_groups_modal.select_button')}
                </Button>
              </div>

              <div className="SelectGroups-selected">
                {this.renderGroupBadges(this.getAbsorberBypassGroups(), this.removeAbsorberBypassGroup.bind(this))}
              </div>

              <p className="helpText">{app.translator.trans('huseyinfiliz-bump.admin.absorber_bypass_groups_help')}</p>
            </div>
          </div>
        </div>

        {/* Submit Button */}
        <div className="Form-group">{submitButton()}</div>
      </div>
    );
  }
}
