declare module 'flarum/common/models/Discussion' {
    export default interface Discussion {
        canBump(): boolean;
        lastBumpedAt(): Date | null;
        lastManualBumpedAt(): Date | null;
        dailyBumpQuota(): {
            used: number;
            limit: number;
            remaining: number;
        } | null;
        weeklyBumpQuota(): {
            used: number;
            limit: number;
            remaining: number;
        } | null;
        bumpCooldownHours(): number;
        canModerateBumps(): boolean;
        isBumpDisabled(): boolean;
    }
}
export {};
