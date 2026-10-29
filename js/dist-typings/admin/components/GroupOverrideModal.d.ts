import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import FormModal from 'flarum/common/components/FormModal';
import Group from 'flarum/common/models/Group';
interface GroupOverrideModalAttrs extends IFormModalAttrs {
    groupId?: string;
    manualOverride?: {
        cooldown?: number;
        daily?: number;
        weekly?: number;
    };
    absorberOverride?: {
        threshold?: number;
    };
    globalSettings: {
        cooldown: number;
        daily: number;
        weekly: number;
        threshold: number;
    };
    existingGroupIds: string[];
    onsubmit: (groupId: string, manualOverride: any, absorberOverride: any) => void;
}
export default class GroupOverrideModal extends FormModal<GroupOverrideModalAttrs> {
    selectedGroupId: string;
    cooldownMode: 'global' | 'custom';
    cooldownValue: string;
    dailyMode: 'global' | 'custom';
    dailyValue: string;
    weeklyMode: 'global' | 'custom';
    weeklyValue: string;
    thresholdMode: 'global' | 'custom';
    thresholdValue: string;
    oninit(vnode: any): void;
    className(): string;
    title(): string | any[];
    content(): JSX.Element;
    onsubmit(e: Event): void;
    getAvailableGroups(): Group[];
    getGroupName(groupId: string): string;
}
export {};
