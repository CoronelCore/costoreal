import type { CostResult, FinancialStatistics, ProductMutation, SalesProjection } from '../../resources/js/Types/financial';

declare const summary: FinancialStatistics;
// @ts-expect-error The summary intentionally has no silently misspelled total.
summary.costo_total_clp;
// @ts-expect-error HTTP money must remain an exact decimal string.
const unsafeMoney: FinancialStatistics = { ...summary, costo_total: 0.1 + 0.2 };
declare const draft: Omit<ProductMutation, 'expected_revision'>;
// @ts-expect-error Existing mutations require an explicit revision.
const unversionedMutation: ProductMutation = draft;
declare const result: CostResult;
// @ts-expect-error Result allocations use amount, not the persisted expense amount.
result.allocations[0].monto_clp;
declare const projection: SalesProjection;
// @ts-expect-error An incomplete projection must not silently become a number.
const assumedRevenue: string = projection.revenue;
void [unsafeMoney, unversionedMutation, assumedRevenue];
