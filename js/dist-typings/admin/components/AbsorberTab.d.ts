/// <reference types="mithril" />
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Group from 'flarum/common/models/Group';
interface AbsorberTabAttrs extends ComponentAttrs {
    buildSettingComponent: (options: any) => any;
    submitButton: () => any;
    setting: (key: string, value?: string) => any;
}
export default class AbsorberTab extends Component<AbsorberTabAttrs> {
    getAbsorberBypassGroups(): Group[];
    openAbsorberBypassGroupsModal(): void;
    removeAbsorberBypassGroup(groupId: string): void;
    renderGroupBadges(groups: Group[], removeCallback: (groupId: string) => void): JSX.Element | JSX.Element[];
    view(): JSX.Element;
}
export {};
