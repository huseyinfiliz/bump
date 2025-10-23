/// <reference types="mithril" />
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
export default class BumpSettingsPage extends ExtensionPage {
    activeTab: string;
    loading: boolean;
    stats: any;
    recentBumps: any[];
    groupOverridesDirty: boolean;
    oninit(vnode: any): void;
    isChanged(): any;
    onsaved(): void;
    content(): JSX.Element;
    tabs(): JSX.Element;
    activeTabContent(): JSX.Element | null;
    loadStats(): void;
}
