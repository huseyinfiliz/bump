import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Group from 'flarum/common/models/Group';
import GroupOverrideModal from './GroupOverrideModal';

interface GroupOverridesTabAttrs extends ComponentAttrs {
  buildSettingComponent: (options: any) => any;
  submitButton: () => any;
  setting: (key: string, fallback?: string) => any;
  onDirty: () => void;
}

export default class GroupOverridesTab extends Component<GroupOverridesTabAttrs> {
  oninit(vnode: any) {
    super.oninit(vnode);

    // Auto-cleanup invalid group IDs on load
    this.autoCleanupInvalidOverrides();
  }

  autoCleanupInvalidOverrides() {
    const manualOverrides = this.getManualOverrides();
    const absorberOverrides = this.getAbsorberOverrides();

    // Check if cleanup removed any invalid entries
    const manualJson = this.attrs.setting('huseyinfiliz-bump.group-overrides-manual')() || '{}';
    const absorberJson = this.attrs.setting('huseyinfiliz-bump.group-overrides-absorber')() || '{}';

    const originalManual = JSON.parse(manualJson);
    const originalAbsorber = JSON.parse(absorberJson);

    const manualChanged = Object.keys(originalManual).length !== Object.keys(manualOverrides).length;
    const absorberChanged = Object.keys(originalAbsorber).length !== Object.keys(absorberOverrides).length;

    // If cleanup removed invalid entries, save the cleaned version
    if (manualChanged || absorberChanged) {
      this.attrs.setting('huseyinfiliz-bump.group-overrides-manual')(JSON.stringify(manualOverrides));
      this.attrs.setting('huseyinfiliz-bump.group-overrides-absorber')(JSON.stringify(absorberOverrides));

      // Mark as dirty and save to persist cleanup
      this.attrs.onDirty();
    }
  }

  view() {
    const { submitButton } = this.attrs;

    // Load overrides fresh on every render
    const manualOverrides = this.getManualOverrides();
    const absorberOverrides = this.getAbsorberOverrides();
    const groupIds = this.getConfiguredGroupIds(manualOverrides, absorberOverrides);

    return (
      <div className="GroupOverridesTab">
        {/* Header */}
        <div className="GroupOverrides-intro">
          <p>{app.translator.trans('huseyinfiliz-bump.admin.group_overrides.description')}</p>
          <p className="helpText">{app.translator.trans('huseyinfiliz-bump.admin.group_overrides.help')}</p>
        </div>

        {/* Add Button */}
        <div className="GroupOverrides-actions">
          <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.showAddModal()}>
            {app.translator.trans('huseyinfiliz-bump.admin.group_overrides.add_override')}
          </Button>
        </div>

        {/* Group Override Cards */}
        {groupIds.length > 0 ? (
          <div className="GroupOverrides-list">{groupIds.map((groupId) => this.renderGroupCard(groupId, manualOverrides, absorberOverrides))}</div>
        ) : (
          <div className="EmptyState">
            <div className="EmptyState-icon">
              <i className="fas fa-users-cog"></i>
            </div>
            <p>{app.translator.trans('huseyinfiliz-bump.admin.group_overrides.no_overrides')}</p>
            <p className="helpText">{app.translator.trans('huseyinfiliz-bump.admin.group_overrides.get_started')}</p>
          </div>
        )}

        {/* Submit Button */}
        <div className="Form-group">{submitButton()}</div>
      </div>
    );
  }

  renderGroupCard(groupId: string, manualOverrides: Record<string, any>, absorberOverrides: Record<string, any>) {
    const group = app.store.getById<Group>('groups', groupId);
    const groupName = group ? group.namePlural() : `Group ${groupId}`;
    const manualOverride = manualOverrides[groupId] || {};
    const absorberOverride = absorberOverrides[groupId] || {};

    const globalSettings = this.getGlobalSettings();

    return (
      <div className="GroupOverrideCard" key={groupId}>
        <div className="GroupOverrideCard-header">
          <div className="GroupOverrideCard-title">
            <i className="fas fa-users"></i>
            <strong>{groupName}</strong>
          </div>
          <div className="GroupOverrideCard-actions">
            <Button className="Button Button--text" icon="fas fa-edit" onclick={() => this.showEditModal(groupId)}>
              {app.translator.trans('huseyinfiliz-bump.admin.group_overrides.edit')}
            </Button>
            <Button className="Button Button--text Button--danger" icon="fas fa-trash" onclick={() => this.removeOverride(groupId)}>
              {app.translator.trans('huseyinfiliz-bump.admin.group_overrides.remove')}
            </Button>
          </div>
        </div>

        <div className="GroupOverrideCard-content">
          {/* Manual Bump Settings */}
          <div className="GroupOverrideCard-section">
            <h4>
              <i className="fas fa-hand-pointer"></i>
              {app.translator.trans('huseyinfiliz-bump.admin.tabs.bump')}
            </h4>
            <div className="GroupOverrideCard-settings">
              {this.renderSetting(
                String(app.translator.trans('huseyinfiliz-bump.admin.manual_cooldown_label')),
                manualOverride.cooldown,
                globalSettings.cooldown,
                'hours'
              )}
              {this.renderSetting(
                String(app.translator.trans('huseyinfiliz-bump.admin.owner_daily_quota_label')),
                manualOverride.daily,
                globalSettings.daily,
                'bumps'
              )}
              {this.renderSetting(
                String(app.translator.trans('huseyinfiliz-bump.admin.owner_weekly_quota_label')),
                manualOverride.weekly,
                globalSettings.weekly,
                'bumps'
              )}
            </div>
          </div>

          {/* Absorber Settings */}
          <div className="GroupOverrideCard-section">
            <h4>
              <i className="fas fa-hourglass-half"></i>
              {app.translator.trans('huseyinfiliz-bump.admin.tabs.absorber')}
            </h4>
            <div className="GroupOverrideCard-settings">
              {this.renderSetting(
                String(app.translator.trans('huseyinfiliz-bump.admin.threshold_hours_label')),
                absorberOverride.threshold,
                globalSettings.threshold,
                'hours'
              )}
            </div>
          </div>
        </div>
      </div>
    );
  }

  renderSetting(label: string, customValue: number | undefined, globalValue: number, unit: string) {
    const isCustom = customValue !== undefined;
    const displayValue = isCustom ? customValue : globalValue;
    const isUnlimited = displayValue === 0;

    return (
      <div className="GroupOverrideSetting">
        <span className="GroupOverrideSetting-label">{label}:</span>
        <span className={'GroupOverrideSetting-value' + (isCustom ? ' GroupOverrideSetting-value--custom' : '')}>
          {isUnlimited ? app.translator.trans('huseyinfiliz-bump.admin.group_overrides.unlimited') : `${displayValue} ${unit}`}
          {!isCustom && (
            <span className="GroupOverrideSetting-badge">{app.translator.trans('huseyinfiliz-bump.admin.group_overrides.using_global')}</span>
          )}
        </span>
      </div>
    );
  }

  getManualOverrides(): Record<string, any> {
    try {
      const manualJson = this.attrs.setting('huseyinfiliz-bump.group-overrides-manual')() || '{}';
      const overrides = JSON.parse(manualJson);

      // Clean up invalid group IDs
      return this.cleanupOverrides(overrides);
    } catch (e) {
      console.error('Failed to parse manual overrides:', e);
      return {};
    }
  }

  getAbsorberOverrides(): Record<string, any> {
    try {
      const absorberJson = this.attrs.setting('huseyinfiliz-bump.group-overrides-absorber')() || '{}';
      const overrides = JSON.parse(absorberJson);

      // Clean up invalid group IDs
      return this.cleanupOverrides(overrides);
    } catch (e) {
      console.error('Failed to parse absorber overrides:', e);
      return {};
    }
  }

  cleanupOverrides(overrides: Record<string, any>): Record<string, any> {
    const cleaned: Record<string, any> = {};

    Object.keys(overrides).forEach((key) => {
      // Only keep valid numeric group IDs
      const num = parseInt(key);
      if (!isNaN(num) && String(num) === key) {
        cleaned[key] = overrides[key];
      }
    });

    return cleaned;
  }

  getConfiguredGroupIds(manualOverrides: Record<string, any>, absorberOverrides: Record<string, any>): string[] {
    const manualIds = Object.keys(manualOverrides);
    const absorberIds = Object.keys(absorberOverrides);

    // Merge and deduplicate using Array.from instead of spread operator
    const allIds = Array.from(new Set([...manualIds, ...absorberIds]));

    // Filter out invalid group IDs (like "[object Set]", "Set(0)", etc)
    // Valid group IDs should be numeric strings like "1", "3", "4"
    const validIds = allIds.filter((id) => {
      // Check if it's a valid number
      const num = parseInt(id, 10);
      return !isNaN(num) && String(num) === id;
    });

    // Sort by group ID (admins first)
    return validIds.sort((a, b) => parseInt(a, 10) - parseInt(b, 10));
  }

  getGlobalSettings() {
    return {
      cooldown: parseInt(app.data.settings['huseyinfiliz-bump.manual-cooldown-hours'] || '0'),
      daily: parseInt(app.data.settings['huseyinfiliz-bump.owner-daily-quota'] || '0'),
      weekly: parseInt(app.data.settings['huseyinfiliz-bump.owner-weekly-quota'] || '0'),
      threshold: parseInt(app.data.settings['huseyinfiliz-bump.threshold-hours'] || '0'),
    };
  }

  showAddModal() {
    const manualOverrides = this.getManualOverrides();
    const absorberOverrides = this.getAbsorberOverrides();

    app.modal.show(GroupOverrideModal, {
      globalSettings: this.getGlobalSettings(),
      existingGroupIds: this.getConfiguredGroupIds(manualOverrides, absorberOverrides),
      onsubmit: (groupId: string, manualOverride: any, absorberOverride: any) => {
        this.saveOverride(groupId, manualOverride, absorberOverride);
      },
    });
  }

  showEditModal(groupId: string) {
    const manualOverrides = this.getManualOverrides();
    const absorberOverrides = this.getAbsorberOverrides();

    app.modal.show(GroupOverrideModal, {
      groupId,
      manualOverride: manualOverrides[groupId],
      absorberOverride: absorberOverrides[groupId],
      globalSettings: this.getGlobalSettings(),
      existingGroupIds: this.getConfiguredGroupIds(manualOverrides, absorberOverrides),
      onsubmit: (gId: string, manualOverride: any, absorberOverride: any) => {
        this.saveOverride(gId, manualOverride, absorberOverride);
      },
    });
  }

  saveOverride(groupId: string, manualOverride: any, absorberOverride: any) {
    // Get current overrides
    const manualOverrides = this.getManualOverrides();
    const absorberOverrides = this.getAbsorberOverrides();

    // Update manual overrides
    if (Object.keys(manualOverride).length > 0) {
      manualOverrides[groupId] = manualOverride;
    } else {
      delete manualOverrides[groupId];
    }

    // Update absorber overrides
    if (Object.keys(absorberOverride).length > 0) {
      absorberOverrides[groupId] = absorberOverride;
    } else {
      delete absorberOverrides[groupId];
    }

    // Save to settings using parent's setting helper
    this.attrs.setting('huseyinfiliz-bump.group-overrides-manual')(JSON.stringify(manualOverrides));
    this.attrs.setting('huseyinfiliz-bump.group-overrides-absorber')(JSON.stringify(absorberOverrides));

    // Mark the page as dirty so Save Changes button becomes active
    this.attrs.onDirty();

    // Force UI redraw to show the new override in the list
    m.redraw();
  }

  removeOverride(groupId: string) {
    if (confirm(String(app.translator.trans('huseyinfiliz-bump.admin.group_overrides.confirm_remove')))) {
      // Get current overrides
      const manualOverrides = this.getManualOverrides();
      const absorberOverrides = this.getAbsorberOverrides();

      // Remove from both override objects
      delete manualOverrides[groupId];
      delete absorberOverrides[groupId];

      // Save to settings using parent's setting helper
      this.attrs.setting('huseyinfiliz-bump.group-overrides-manual')(JSON.stringify(manualOverrides));
      this.attrs.setting('huseyinfiliz-bump.group-overrides-absorber')(JSON.stringify(absorberOverrides));

      // Mark the page as dirty so Save Changes button becomes active
      this.attrs.onDirty();

      // Force UI redraw to remove the override from the list
      m.redraw();
    }
  }
}
