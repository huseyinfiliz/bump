import app from 'flarum/admin/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import humanTime from 'flarum/common/helpers/humanTime';
import Link from 'flarum/common/components/Link';

interface RecentBumpsTabAttrs extends ComponentAttrs {
  recentBumps: any[];
  loading: boolean;
}

export default class RecentBumpsTab extends Component<RecentBumpsTabAttrs> {
  view() {
    const { recentBumps, loading } = this.attrs;

    if (loading) {
      return (
        <div className="LoadingState">
          <div className="LoadingIndicator"></div>
          <p>{app.translator.trans('huseyinfiliz-bump.admin.recent_bumps.loading')}</p>
        </div>
      );
    }

    if (!recentBumps || recentBumps.length === 0) {
      return (
        <div className="EmptyState">
          <div className="EmptyState-icon">
            <i className="fas fa-level-up-alt"></i>
          </div>
          <h3>{app.translator.trans('huseyinfiliz-bump.admin.recent_bumps.title')}</h3>
          <p>{app.translator.trans('huseyinfiliz-bump.admin.recent_bumps.no_bumps')}</p>
        </div>
      );
    }

    return (
      <div className="RecentBumpsList">
        <div className="RecentBumps-header">
          <h3>
            <i className="fas fa-history"></i>
            {app.translator.trans('huseyinfiliz-bump.admin.recent_bumps.title')}
          </h3>
          <span className="RecentBumps-count">
            {app.translator.trans('huseyinfiliz-bump.admin.recent_bumps.showing', {
              count: recentBumps.length,
            })}
          </span>
        </div>

        <div className="RecentBumps-list">{recentBumps.map((bump: any) => this.bumpCard(bump))}</div>
      </div>
    );
  }

  bumpCard(bump: any) {
    const discussionUrl = app.forum.attribute('baseUrl') + '/d/' + bump.discussion_id + '-' + bump.discussion_slug;

    return (
      <div key={bump.id} className="BumpCard BumpCard--manual">
        <div className="BumpCard-header">
          <div className="BumpCard-discussion">
            <i className="fas fa-comments"></i>
            <a href={discussionUrl} target="_blank" rel="noopener noreferrer">
              <strong>{bump.discussion_title || app.translator.trans('huseyinfiliz-bump.admin.recent_bumps.untitled')}</strong>
            </a>
          </div>

          <div className="BumpCard-meta">
            <span className="BumpCard-typeBadge BumpCard-typeBadge--manual">
              <i className="fas fa-hand-pointer"></i>
              <span>{app.translator.trans('huseyinfiliz-bump.admin.recent_bumps.type_manual')}</span>
            </span>

            <span className="BumpCard-dateBadge">
              <i className="far fa-clock"></i>
              <span>{humanTime(new Date(bump.created_at))}</span>
            </span>
          </div>
        </div>

        <div className="BumpCard-footer">
          {bump.username && (
            <div className="BumpCard-user">
              <i className="fas fa-user"></i>
              <span>{bump.username}</span>
            </div>
          )}
          {!bump.username && (
            <div className="BumpCard-user">
              <i className="fas fa-robot"></i>
              <span>{app.translator.trans('huseyinfiliz-bump.admin.recent_bumps.system')}</span>
            </div>
          )}

          <a href={discussionUrl} target="_blank" rel="noopener noreferrer" className="BumpCard-link">
            {app.translator.trans('huseyinfiliz-bump.admin.recent_bumps.view_discussion')}
            <i className="fas fa-external-link-alt"></i>
          </a>
        </div>
      </div>
    );
  }
}
