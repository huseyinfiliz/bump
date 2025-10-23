/// <reference types="mithril" />
import Component, { ComponentAttrs } from 'flarum/common/Component';
interface RecentBumpsTabAttrs extends ComponentAttrs {
    recentBumps: any[];
    loading: boolean;
}
export default class RecentBumpsTab extends Component<RecentBumpsTabAttrs> {
    view(): JSX.Element;
    bumpCard(bump: any): JSX.Element;
}
export {};
