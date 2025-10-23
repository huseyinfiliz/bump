/// <reference types="mithril" />
import Component, { ComponentAttrs } from 'flarum/common/Component';
interface GroupOverridesTabAttrs extends ComponentAttrs {
    buildSettingComponent: (options: any) => any;
    submitButton: () => any;
    setting: (key: string, fallback?: string) => any;
    onDirty: () => void;
}
export default class GroupOverridesTab extends Component<GroupOverridesTabAttrs> {
    oninit(vnode: any): void;
    autoCleanupInvalidOverrides(): void;
    view(): JSX.Element;
    renderGroupCard(groupId: string, manualOverrides: Record<string, any>, absorberOverrides: Record<string, any>): JSX.Element;
    renderSetting(label: string, customValue: number | undefined, globalValue: number, unit: string): JSX.Element;
    getManualOverrides(): Record<string, any>;
    getAbsorberOverrides(): Record<string, any>;
    cleanupOverrides(overrides: Record<string, any>): Record<string, any>;
    getConfiguredGroupIds(manualOverrides: Record<string, any>, absorberOverrides: Record<string, any>): string[];
    getGlobalSettings(): {
        cooldown: number;
        daily: number;
        weekly: number;
        threshold: number;
    };
    showAddModal(): void;
    showEditModal(groupId: string): void;
    saveOverride(groupId: string, manualOverride: any, absorberOverride: any): void;
    removeOverride(groupId: string): void;
}
export {};
