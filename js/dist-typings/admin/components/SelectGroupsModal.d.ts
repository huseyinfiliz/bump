/// <reference types="mithril" />
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Group from 'flarum/common/models/Group';
interface SelectGroupsModalAttrs extends IInternalModalAttrs {
    selectedGroupIds: string[];
    onsubmit: (selectedGroupIds: string[]) => void;
}
export default class SelectGroupsModal extends Modal<SelectGroupsModalAttrs> {
    selectedGroupIds: Set<string>;
    oninit(vnode: any): void;
    className(): string;
    title(): any;
    content(): JSX.Element;
    groupItem(group: Group): JSX.Element;
    onsubmit(e: Event): void;
    getAvailableGroups(): Group[];
}
export {};
