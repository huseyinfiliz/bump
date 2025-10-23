/// <reference types="mithril" />
import Component, { ComponentAttrs } from 'flarum/common/Component';
interface StatsCardsAttrs extends ComponentAttrs {
    stats: any;
}
export default class StatsCards extends Component<StatsCardsAttrs> {
    view(): JSX.Element;
    card(data: any): JSX.Element;
}
export {};
