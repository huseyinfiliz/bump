import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import Button from 'flarum/common/components/Button';
import Discussion from 'flarum/common/models/Discussion';
import humanTime from 'flarum/common/helpers/humanTime';

app.initializers.add('huseyinfiliz/bump', () => {
  extend(DiscussionControls, 'moderationControls', function (items, discussion: Discussion) {
    const manualBumpEnabled = app.forum.attribute('huseyinfiliz-bump.enable-manual-bump');

    if (!manualBumpEnabled) {
      return; 
    }

    if (!discussion.canBump()) {
      return; 
    }

    const cooldownHours = discussion.bumpCooldownHours() || 0;
    const isModerator = discussion.canModerateBumps();
    const isBumpDisabled = discussion.isBumpDisabled();
    const lastManualBumpedAt = discussion.lastManualBumpedAt();
    const dailyQuota = discussion.dailyBumpQuota();
    const weeklyQuota = discussion.weeklyBumpQuota();

    let canBumpNow = true;
    let buttonText = app.translator.trans('huseyinfiliz-bump.forum.bump_button');
    let tooltipText = buttonText;
    let disableReason: string = '';
    let cooldownMinutes = 0;

    if (isBumpDisabled) {
      canBumpNow = false;
      disableReason = String(app.translator.trans('huseyinfiliz-bump.forum.bump_disabled_for_group'));
      buttonText = app.translator.trans('huseyinfiliz-bump.forum.bump_disabled');
      tooltipText = disableReason;
    }

    if (!isModerator && !isBumpDisabled) {
      if (cooldownHours > 0 && lastManualBumpedAt) {
        const hoursSince = (Date.now() - lastManualBumpedAt.getTime()) / (1000 * 60 * 60);

        if (hoursSince < cooldownHours) {
          canBumpNow = false;
          cooldownMinutes = Math.ceil((cooldownHours - hoursSince) * 60);
          const nextBumpTime = new Date(lastManualBumpedAt.getTime() + cooldownHours * 60 * 60 * 1000);
          disableReason = String(
            app.translator.trans('huseyinfiliz-bump.forum.bump_cooldown', {
              time: humanTime(nextBumpTime),
            })
          );
        }
      }

      if (canBumpNow && dailyQuota && dailyQuota.remaining <= 0) {
        canBumpNow = false;
        disableReason = String(app.translator.trans('huseyinfiliz-bump.forum.bump_quota_exceeded_daily'));
      }

      if (canBumpNow && weeklyQuota && weeklyQuota.remaining <= 0) {
        canBumpNow = false;
        disableReason = String(app.translator.trans('huseyinfiliz-bump.forum.bump_quota_exceeded_weekly'));
      }

      if (cooldownMinutes > 0) {
        const timeText = formatCooldownTime(cooldownMinutes);
        buttonText = app.translator.trans('huseyinfiliz-bump.forum.bump_button_cooldown', { time: timeText });
        tooltipText = disableReason;
      } else {
        let quotaToShow = null;
        let quotaInfo: string[] = [];

        if (dailyQuota && dailyQuota.limit > 0) {
          quotaToShow = dailyQuota;
          quotaInfo.push(`Daily: ${dailyQuota.remaining}/${dailyQuota.limit}`);
        }

        if (weeklyQuota && weeklyQuota.limit > 0) {
          quotaInfo.push(`Weekly: ${weeklyQuota.remaining}/${weeklyQuota.limit}`);

          if (!quotaToShow || weeklyQuota.remaining < quotaToShow.remaining) {
            quotaToShow = weeklyQuota;
          }
        }

        if (quotaToShow) {
          buttonText = app.translator.trans('huseyinfiliz-bump.forum.bump_button_quota', { count: quotaToShow.remaining });
          tooltipText = quotaInfo.join(', ');
        } else if (!canBumpNow) {
          tooltipText = disableReason;
        }
      }
    }

    function formatCooldownTime(minutes: number): string {
      if (minutes < 60) {
        return `${minutes}m`;
      }
      const hours = Math.floor(minutes / 60);
      const mins = minutes % 60;
      return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
    }

    items.add(
      'bump',
      Button.component(
        {
          icon: 'fas fa-level-up-alt',
          disabled: !canBumpNow,
          title: tooltipText,
          onclick: canBumpNow
            ? () => {
                app.alerts.clear();

                app
                  .request({
                    method: 'POST',
                    url: app.forum.attribute('apiUrl') + '/manual-bump/' + discussion.id(),
                  })
                  .then(
                    () => {
                      app.discussions.refresh();
                      m.redraw();

                      app.alerts.show({ type: 'success' }, app.translator.trans('huseyinfiliz-bump.forum.bump_success'));
                    },
                    (error) => {
                      const message = error.response?.errors?.[0]?.detail || app.translator.trans('huseyinfiliz-bump.forum.bump_error');

                      app.alerts.show({ type: 'error' }, message);
                    }
                  );
              }
            : undefined,
        },
        buttonText
      ),
      -1
    );
  });
});

declare module 'flarum/common/models/Discussion' {
  export default interface Discussion {
    canBump(): boolean;
    lastBumpedAt(): Date | null;
    lastManualBumpedAt(): Date | null;
    dailyBumpQuota(): { used: number; limit: number; remaining: number } | null;
    weeklyBumpQuota(): { used: number; limit: number; remaining: number } | null;
    bumpCooldownHours(): number;
    canModerateBumps(): boolean;
    isBumpDisabled(): boolean;
  }
}

Discussion.prototype.canBump = function () {
  return this.attribute<boolean>('canBump');
};

Discussion.prototype.lastBumpedAt = function () {
  const timestamp = this.attribute<string>('lastBumpedAt');
  return timestamp ? new Date(timestamp) : null;
};

Discussion.prototype.lastManualBumpedAt = function () {
  const timestamp = this.attribute<string>('lastManualBumpedAt');
  return timestamp ? new Date(timestamp) : null;
};

Discussion.prototype.dailyBumpQuota = function () {
  return this.attribute<{ used: number; limit: number; remaining: number } | null>('dailyBumpQuota');
};

Discussion.prototype.weeklyBumpQuota = function () {
  return this.attribute<{ used: number; limit: number; remaining: number } | null>('weeklyBumpQuota');
};

Discussion.prototype.bumpCooldownHours = function () {
  return this.attribute<number>('bumpCooldownHours') || 0;
};

Discussion.prototype.canModerateBumps = function () {
  return this.attribute<boolean>('canModerateBumps') || false;
};

Discussion.prototype.isBumpDisabled = function () {
  return this.attribute<boolean>('isBumpDisabled') || false;
};