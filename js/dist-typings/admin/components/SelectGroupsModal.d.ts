import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import FormModal from 'flarum/common/components/FormModal';
import Group from 'flarum/common/models/Group';
interface SelectGroupsModalAttrs extends IFormModalAttrs {
    selectedGroupIds: string[];
    onsubmit: (selectedGroupIds: string[]) => void;
}
export default class SelectGroupsModal extends FormModal<SelectGroupsModalAttrs> {
    selectedGroupIds: Set<string>;
    oninit(vnode: any): void;
    className(): string;
    title(): string | any[];
    content(): JSX.Element;
    groupItem(group: Group): JSX.Element;
    onsubmit(e: Event): void;
    getAvailableGroups(): Group[];
}
export {};
