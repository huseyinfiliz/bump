import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Group from 'flarum/common/models/Group';

interface SelectGroupsModalAttrs extends IInternalModalAttrs {
  selectedGroupIds: string[];
  onsubmit: (selectedGroupIds: string[]) => void;
}

export default class SelectGroupsModal extends Modal<SelectGroupsModalAttrs> {
  selectedGroupIds: Set<string> = new Set();

  oninit(vnode: any) {
    super.oninit(vnode);

    // Initialize with currently selected groups
    this.selectedGroupIds = new Set(this.attrs.selectedGroupIds || []);
  }

  className() {
    return 'SelectGroupsModal Modal--small';
  }

  title() {
    return app.translator.trans('huseyinfiliz-bump.admin.select_groups_modal.title');
  }

  content() {
    const groups = this.getAvailableGroups();

    return (
      <div className="Modal-body">
        <div className="Form">
          {/* Groups List */}
          <div className="SelectGroups-list">
            {groups.length === 0 ? (
              <p className="SelectGroups-empty">{app.translator.trans('huseyinfiliz-bump.admin.select_groups_modal.no_groups')}</p>
            ) : (
              groups.map((group) => this.groupItem(group))
            )}
          </div>

          {/* Buttons */}
          <div className="Form-group">
            <Button className="Button Button--primary" type="submit" onclick={this.onsubmit.bind(this)}>
              {app.translator.trans('huseyinfiliz-bump.admin.select_groups_modal.save')}
            </Button>
            <Button className="Button" onclick={() => this.hide()}>
              {app.translator.trans('huseyinfiliz-bump.admin.select_groups_modal.cancel')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  groupItem(group: Group) {
    const groupId = String(group.id());
    const isSelected = this.selectedGroupIds.has(groupId);
    const icon = group.icon();
    const color = group.color();

    return (
      <label className={'SelectGroups-item' + (isSelected ? ' is-selected' : '')} key={groupId}>
        <input
          type="checkbox"
          checked={isSelected}
          onchange={(e: any) => {
            if (e.target.checked) {
              this.selectedGroupIds.add(groupId);
            } else {
              this.selectedGroupIds.delete(groupId);
            }
            m.redraw();
          }}
        />
        <span className="SelectGroups-item-label">
          {icon && (
            <span className="SelectGroups-item-icon" style={{ color: color || undefined }}>
              <i className={'icon ' + icon}></i>
            </span>
          )}
          <span className="SelectGroups-item-name">{group.namePlural()}</span>
          {group.isHidden() && (
            <span className="Badge Badge--size-small" style={{ marginLeft: '8px' }}>
              {app.translator.trans('core.admin.groups.hidden')}
            </span>
          )}
        </span>
        {isSelected && (
          <span className="SelectGroups-item-check">
            <i className="fas fa-check"></i>
          </span>
        )}
      </label>
    );
  }

  onsubmit(e: Event) {
    e.preventDefault();

    // Convert Set to Array
    const selectedGroupIds = Array.from(this.selectedGroupIds);

    this.attrs.onsubmit(selectedGroupIds);
    this.hide();
  }

  getAvailableGroups(): Group[] {
    const allGroups = app.store.all<Group>('groups');

    // Exclude Guest group (id: 2) and sort by name
    return allGroups
      .filter((group) => group.id() !== '2')
      .sort((a, b) => {
        const nameA = a.namePlural().toLowerCase();
        const nameB = b.namePlural().toLowerCase();
        return nameA.localeCompare(nameB);
      });
  }
}
