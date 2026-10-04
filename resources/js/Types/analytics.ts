import type { Decimal, ImportState, Pagination, SalesProjection } from './financial';
export interface AnalyticsFilters { from?: string; to?: string; state?: ImportState | '' }
export interface AnalyticsImport {
    id: number; name: string; date: string | null; state: ImportState;
    purchase: Decimal; expenses: Decimal; total: Decimal; unassigned: Decimal; projection: SalesProjection;
}
export interface AnalyticsCategory { id: number; name: string; count: number; total: Decimal }
export interface AnalyticsMonth { month: string; count: number; purchase: Decimal; expenses: Decimal; total: Decimal }
export interface AnalyticsExpense { id: number; trip_id: number; trip_name: string; name: string; category: string; date: string | null; amount: Decimal }
export interface AnalyticsProps {
    metrics: { count: number; active: number; purchase: Decimal; expenses: Decimal; total: Decimal; average: Decimal; unassigned: Decimal };
    projection: SalesProjection; imports: Pagination<AnalyticsImport>; categories: Pagination<AnalyticsCategory>;
    months: Pagination<AnalyticsMonth>; latestExpenses: AnalyticsExpense[]; filters: AnalyticsFilters;
}
