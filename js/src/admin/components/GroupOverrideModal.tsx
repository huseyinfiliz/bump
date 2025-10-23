import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Group from 'flarum/common/models/Group';

interface GroupOverrideModalAttrs extends IInternalModalAttrs {
  groupId?: string;
  manualOverride?: {
    cooldown?: number;
    daily?: number;
    weekly?: number;
  };
  absorberOverride?: {
    threshold?: number;
  };
  globalSettings: {
    cooldown: number;
    daily: number;
    weekly: number;
    threshold: number;
  };
  existingGroupIds: string[];
  onsubmit: (groupId: string, manualOverride: any, absorberOverride: any) => void;
}

export default class GroupOverrideModal extends Modal<GroupOverrideModalAttrs> {
  selectedGroupId!: string;

  // Manual bump settings
  cooldownMode: 'global' | 'custom' = 'global';
  cooldownValue: string = '0';

  dailyMode: 'global' | 'custom' = 'global';
  dailyValue: string = '0';

  weeklyMode: 'global' | 'custom' = 'global';
  weeklyValue: string = '0';

  // Absorber settings
  thresholdMode: 'global' | 'custom' = 'global';
  thresholdValue: string = '0';

  oninit(vnode: any) {
    super.oninit(vnode);

    // If editing existing override
    if (this.attrs.groupId) {
      this.selectedGroupId = this.attrs.groupId;

      // Load manual settings
      if (this.attrs.manualOverride) {
        if (this.attrs.manualOverride.cooldown !== undefined) {
          this.cooldownMode = 'custom';
          this.cooldownValue = String(this.attrs.manualOverride.cooldown);
        }
        if (this.attrs.manualOverride.daily !== undefined) {
          this.dailyMode = 'custom';
          this.dailyValue = String(this.attrs.manualOverride.daily);
        }
        if (this.attrs.manualOverride.weekly !== undefined) {
          this.weeklyMode = 'custom';
          this.weeklyValue = String(this.attrs.manualOverride.weekly);
        }
      }

      // Load absorber settings
      if (this.attrs.absorberOverride) {
        if (this.attrs.absorberOverride.threshold !== undefined) {
          this.thresholdMode = 'custom';
          this.thresholdValue = String(this.attrs.absorberOverride.threshold);
        }
      }
    } else {
      // Default to first available group
      const availableGroups = this.getAvailableGroups();
      this.selectedGroupId = String(availableGroups[0]?.id() || '');
    }
  }

  className() {
    return 'GroupOverrideModal Modal--large';
  }

  title() {
    return this.attrs.groupId
      ? app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.edit_title')
      : app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.add_title');
  }

  content() {
    const globalSettings = this.attrs.globalSettings;

    return (
      <div className="Modal-body">
        <form onsubmit={this.onsubmit.bind(this)} className="Form">
          {/* Group Selector */}
          <div className="Form-group">
            <label>{app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.select_group')}</label>
            {this.attrs.groupId ? (
              <input className="FormControl" type="text" value={this.getGroupName(this.selectedGroupId)} disabled />
            ) : (
              <select
                className="FormControl"
                value={this.selectedGroupId}
                oninput={(e: any) => {
                  this.selectedGroupId = e.target.value;
                }}
              >
                {this.getAvailableGroups().map((group) => (
                  <option key={group.id()} value={String(group.id())}>
                    {group.namePlural()}
                  </option>
                ))}
              </select>
            )}
          </div>

          {/* Manual Bump Settings Section */}
          <div className="SettingsSection">
            <h3>
              <i className="fas fa-hand-pointer"></i>
              {app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.manual_section')}
            </h3>

            {/* Cooldown */}
            <div className="Form-group">
              <label>{app.translator.trans('huseyinfiliz-bump.admin.manual_cooldown_label')}</label>
              <div className="GroupOverride-fieldset">
                <label className="GroupOverride-radio">
                  <input
                    type="radio"
                    name="cooldown-mode"
                    checked={this.cooldownMode === 'global'}
                    onclick={() => {
                      this.cooldownMode = 'global';
                    }}
                  />
                  <span>
                    {app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.use_global', {
                      value: globalSettings.cooldown,
                    })}
                  </span>
                </label>
                <label className="GroupOverride-radio">
                  <input
                    type="radio"
                    name="cooldown-mode"
                    checked={this.cooldownMode === 'custom'}
                    onclick={() => {
                      this.cooldownMode = 'custom';
                    }}
                  />
                  <span>{app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.custom_value')}</span>
                  <input
                    className="FormControl GroupOverride-input"
                    type="number"
                    min="-1"
                    value={this.cooldownValue}
                    disabled={this.cooldownMode === 'global'}
                    oninput={(e: any) => {
                      this.cooldownValue = e.target.value;
                    }}
                  />
                </label>
              </div>
              <p className="helpText">{app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.minus_one_disabled_zero_unlimited')}</p>
            </div>

            {/* Daily Quota */}
            <div className="Form-group">
              <label>{app.translator.trans('huseyinfiliz-bump.admin.owner_daily_quota_label')}</label>
              <div className="GroupOverride-fieldset">
                <label className="GroupOverride-radio">
                  <input
                    type="radio"
                    name="daily-mode"
                    checked={this.dailyMode === 'global'}
                    onclick={() => {
                      this.dailyMode = 'global';
                    }}
                  />
                  <span>
                    {app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.use_global', {
                      value: globalSettings.daily,
                    })}
                  </span>
                </label>
                <label className="GroupOverride-radio">
                  <input
                    type="radio"
                    name="daily-mode"
                    checked={this.dailyMode === 'custom'}
                    onclick={() => {
                      this.dailyMode = 'custom';
                    }}
                  />
                  <span>{app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.custom_value')}</span>
                  <input
                    className="FormControl GroupOverride-input"
                    type="number"
                    min="-1"
                    value={this.dailyValue}
                    disabled={this.dailyMode === 'global'}
                    oninput={(e: any) => {
                      this.dailyValue = e.target.value;
                    }}
                  />
                </label>
              </div>
              <p className="helpText">{app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.minus_one_disabled_zero_unlimited')}</p>
            </div>

            {/* Weekly Quota */}
            <div className="Form-group">
              <label>{app.translator.trans('huseyinfiliz-bump.admin.owner_weekly_quota_label')}</label>
              <div className="GroupOverride-fieldset">
                <label className="GroupOverride-radio">
                  <input
                    type="radio"
                    name="weekly-mode"
                    checked={this.weeklyMode === 'global'}
                    onclick={() => {
                      this.weeklyMode = 'global';
                    }}
                  />
                  <span>
                    {app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.use_global', {
                      value: globalSettings.weekly,
                    })}
                  </span>
                </label>
                <label className="GroupOverride-radio">
                  <input
                    type="radio"
                    name="weekly-mode"
                    checked={this.weeklyMode === 'custom'}
                    onclick={() => {
                      this.weeklyMode = 'custom';
                    }}
                  />
                  <span>{app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.custom_value')}</span>
                  <input
                    className="FormControl GroupOverride-input"
                    type="number"
                    min="-1"
                    value={this.weeklyValue}
                    disabled={this.weeklyMode === 'global'}
                    oninput={(e: any) => {
                      this.weeklyValue = e.target.value;
                    }}
                  />
                </label>
              </div>
              <p className="helpText">{app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.minus_one_disabled_zero_unlimited')}</p>
            </div>
          </div>

          {/* Absorber Settings Section */}
          <div className="SettingsSection">
            <h3>
              <i className="fas fa-hourglass-half"></i>
              {app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.absorber_section')}
            </h3>

            {/* Threshold */}
            <div className="Form-group">
              <label>{app.translator.trans('huseyinfiliz-bump.admin.threshold_hours_label')}</label>
              <div className="GroupOverride-fieldset">
                <label className="GroupOverride-radio">
                  <input
                    type="radio"
                    name="threshold-mode"
                    checked={this.thresholdMode === 'global'}
                    onclick={() => {
                      this.thresholdMode = 'global';
                    }}
                  />
                  <span>
                    {app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.use_global', {
                      value: globalSettings.threshold,
                    })}
                  </span>
                </label>
                <label className="GroupOverride-radio">
                  <input
                    type="radio"
                    name="threshold-mode"
                    checked={this.thresholdMode === 'custom'}
                    onclick={() => {
                      this.thresholdMode = 'custom';
                    }}
                  />
                  <span>{app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.custom_value')}</span>
                  <input
                    className="FormControl GroupOverride-input"
                    type="number"
                    min="0"
                    value={this.thresholdValue}
                    disabled={this.thresholdMode === 'global'}
                    oninput={(e: any) => {
                      this.thresholdValue = e.target.value;
                    }}
                  />
                </label>
              </div>
              <p className="helpText">{app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.zero_means_bypass')}</p>
            </div>
          </div>

          {/* Submit Buttons */}
          <div className="Form-group">
            <Button className="Button Button--primary" type="submit" loading={this.loading}>
              {app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.save')}
            </Button>
            <Button className="Button" onclick={() => this.hide()}>
              {app.translator.trans('huseyinfiliz-bump.admin.group_override_modal.cancel')}
            </Button>
          </div>
        </form>
      </div>
    );
  }

  onsubmit(e: Event) {
    e.preventDefault();

    // Build manual override object
    const manualOverride: any = {};
    if (this.cooldownMode === 'custom') {
      manualOverride.cooldown = parseInt(this.cooldownValue) || 0;
    }
    if (this.dailyMode === 'custom') {
      manualOverride.daily = parseInt(this.dailyValue) || 0;
    }
    if (this.weeklyMode === 'custom') {
      manualOverride.weekly = parseInt(this.weeklyValue) || 0;
    }

    // Build absorber override object
    const absorberOverride: any = {};
    if (this.thresholdMode === 'custom') {
      absorberOverride.threshold = parseInt(this.thresholdValue) || 0;
    }

    this.attrs.onsubmit(this.selectedGroupId, manualOverride, absorberOverride);
    this.hide();
  }

  getAvailableGroups(): Group[] {
    const allGroups = app.store.all<Group>('groups');
    return allGroups.filter((group) => {
      // Exclude Guest group (id: 2) and existing groups
      const groupIdStr = String(group.id());
      return groupIdStr !== '2' && !this.attrs.existingGroupIds.includes(groupIdStr);
    });
  }

  getGroupName(groupId: string): string {
    const group = app.store.getById<Group>('groups', groupId);
    return group ? group.namePlural() : groupId;
  }
}
