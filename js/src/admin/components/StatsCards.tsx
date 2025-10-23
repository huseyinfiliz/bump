import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import humanTime from 'flarum/common/helpers/humanTime';

interface StatsCardsAttrs extends ComponentAttrs {
  stats: any;
}

export default class StatsCards extends Component<StatsCardsAttrs> {
  view() {
    const stats = this.attrs.stats || {
      totalBumps: 0,
      todayBumps: 0,
      weekBumps: 0,
      absorberActive: false,
      lastBumpDate: null,
    };

    return (
      <div className="BumpStats">
        {this.card({
          icon: 'fas fa-level-up-alt',
          value: stats.totalBumps,
          label: app.translator.trans('huseyinfiliz-bump.admin.stats.total_bumps'),
          type: 'total',
        })}

        {this.card({
          icon: 'fas fa-calendar-day',
          value: stats.todayBumps,
          label: app.translator.trans('huseyinfiliz-bump.admin.stats.today_bumps'),
          type: 'today',
        })}

        {this.card({
          icon: 'fas fa-calendar-week',
          value: stats.weekBumps,
          label: app.translator.trans('huseyinfiliz-bump.admin.stats.week_bumps'),
          type: 'week',
        })}

        {this.card({
          icon: stats.lastBumpDate ? 'far fa-clock' : 'fas fa-hourglass-start',
          value: stats.lastBumpDate ? humanTime(new Date(stats.lastBumpDate)) : app.translator.trans('huseyinfiliz-bump.admin.stats.no_bumps_yet'),
          label: app.translator.trans('huseyinfiliz-bump.admin.stats.last_bump'),
          type: 'last',
          noNumber: true,
        })}
      </div>
    );
  }

  card(data: any) {
    return (
      <div className={'StatsCard StatsCard--' + data.type}>
        <div className="StatsCard-icon">
          <i className={data.icon}></i>
        </div>
        <div className="StatsCard-content">
          {!data.noNumber && <div className="StatsCard-value">{data.value}</div>}
          {data.noNumber && <div className="StatsCard-status">{data.value}</div>}
          <div className="StatsCard-label">{data.label}</div>
        </div>
      </div>
    );
  }
}
