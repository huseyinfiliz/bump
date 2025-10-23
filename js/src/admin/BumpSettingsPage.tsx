import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import StatsCards from './components/StatsCards';
import BumpTab from './components/BumpTab';
import AbsorberTab from './components/AbsorberTab';
import GroupOverridesTab from './components/GroupOverridesTab';
import RecentBumpsTab from './components/RecentBumpsTab';

export default class BumpSettingsPage extends ExtensionPage {
  activeTab: string = 'bump';
  loading: boolean = false;
  stats: any = null;
  recentBumps: any[] = [];
  groupOverridesDirty: boolean = false;

  oninit(vnode: any) {
    super.oninit(vnode);

    // Register settings with ExtensionPage's built-in system
    this.setting('huseyinfiliz-bump.enable-absorber', 'false');
    this.setting('huseyinfiliz-bump.threshold-hours', '0');
    this.setting('huseyinfiliz-bump.absorber-tags', '[]');
    this.setting('huseyinfiliz-bump.absorber-bypass-groups', '[]');
    this.setting('huseyinfiliz-bump.enable-manual-bump', 'false');
    this.setting('huseyinfiliz-bump.manual-bump-tags', '[]');
    this.setting('huseyinfiliz-bump.manual-cooldown-hours', '0');
    this.setting('huseyinfiliz-bump.moderator-groups', '[]');
    this.setting('huseyinfiliz-bump.owner-daily-quota', '0');
    this.setting('huseyinfiliz-bump.owner-weekly-quota', '0');

    // Register group overrides settings
    this.setting('huseyinfiliz-bump.group-overrides-manual', '{}');
    this.setting('huseyinfiliz-bump.group-overrides-absorber', '{}');

    this.loadStats();
  }

  isChanged() {
    return super.isChanged() || (this.groupOverridesDirty ? 1 : 0);
  }

  onsaved() {
    super.onsaved();
    this.groupOverridesDirty = false;

    // Clear bump settings cache on backend
    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/bump/clear-cache',
    });
  }

  content() {
    return (
      <div className="BumpPage">
        <StatsCards stats={this.stats} />
        {this.tabs()}
        <div className="BumpPage-content">{this.activeTabContent()}</div>
      </div>
    );
  }

  tabs() {
    return (
      <div className="BumpTabs">
        <button
          className={'TabButton' + (this.activeTab === 'bump' ? ' active' : '')}
          onclick={() => {
            this.activeTab = 'bump';
          }}
        >
          <i className="fas fa-hand-pointer"></i>
          <span>{app.translator.trans('huseyinfiliz-bump.admin.tabs.bump')}</span>
        </button>

        <button
          className={'TabButton' + (this.activeTab === 'absorber' ? ' active' : '')}
          onclick={() => {
            this.activeTab = 'absorber';
          }}
        >
          <i className="fas fa-hourglass-half"></i>
          <span>{app.translator.trans('huseyinfiliz-bump.admin.tabs.absorber')}</span>
        </button>

        <button
          className={'TabButton' + (this.activeTab === 'groups' ? ' active' : '')}
          onclick={() => {
            this.activeTab = 'groups';
          }}
        >
          <i className="fas fa-users-cog"></i>
          <span>{app.translator.trans('huseyinfiliz-bump.admin.tabs.group_overrides')}</span>
        </button>

        <button
          className={'TabButton' + (this.activeTab === 'recent' ? ' active' : '')}
          onclick={() => {
            this.activeTab = 'recent';
          }}
        >
          <i className="fas fa-history"></i>
          <span>{app.translator.trans('huseyinfiliz-bump.admin.tabs.recent_bumps')}</span>
          {this.recentBumps.length > 0 && <span className="TabButton-badge">{this.recentBumps.length}</span>}
        </button>
      </div>
    );
  }

  activeTabContent() {
    switch (this.activeTab) {
      case 'bump':
        return (
          <BumpTab
            buildSettingComponent={this.buildSettingComponent.bind(this)}
            submitButton={this.submitButton.bind(this)}
            setting={this.setting.bind(this)}
          />
        );
      case 'absorber':
        return (
          <AbsorberTab
            buildSettingComponent={this.buildSettingComponent.bind(this)}
            submitButton={this.submitButton.bind(this)}
            setting={this.setting.bind(this)}
          />
        );
      case 'groups':
        return (
          <GroupOverridesTab
            buildSettingComponent={this.buildSettingComponent.bind(this)}
            submitButton={this.submitButton.bind(this)}
            setting={this.setting.bind(this)}
            onDirty={() => {
              this.groupOverridesDirty = true;
              m.redraw();
            }}
          />
        );
      case 'recent':
        return <RecentBumpsTab recentBumps={this.recentBumps} loading={this.loading} />;
      default:
        return null;
    }
  }

  loadStats() {
    this.loading = true;
    m.redraw();

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/bump/stats',
      })
      .then((response: any) => {
        const data = response.data?.attributes || response;

        this.stats = {
          totalBumps: data.totalBumps || 0,
          todayBumps: data.todayBumps || 0,
          weekBumps: data.weekBumps || 0,
          absorberActive: data.absorberActive || false,
          lastBumpDate: data.lastBumpDate || null,
        };

        this.recentBumps = data.recentBumps || [];
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        // Failed to load stats, use defaults
        this.stats = {
          totalBumps: 0,
          todayBumps: 0,
          weekBumps: 0,
          absorberActive: false,
          lastBumpDate: null,
        };
        this.recentBumps = [];
        this.loading = false;
        m.redraw();
      });
  }
}
