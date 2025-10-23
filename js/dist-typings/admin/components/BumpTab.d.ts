/// <reference types="mithril" />
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Group from 'flarum/common/models/Group';
interface BumpTabAttrs extends ComponentAttrs {
    buildSettingComponent: (options: any) => any;
    submitButton: () => any;
    setting: (key: string, value?: string) => any;
}
export default class BumpTab extends Component<BumpTabAttrs> {
    getModeratorGroups(): Group[];
    openModeratorGroupsModal(): void;
    removeModeratorGroup(groupId: string): void;
    renderGroupBadges(groups: Group[], removeCallback: (groupId: string) => void): JSX.Element | JSX.Element[];
    view(): JSX.Element;
}
export {};
