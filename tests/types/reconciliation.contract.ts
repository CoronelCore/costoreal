import type { CostComparison, ReconciliationApply } from '../../resources/js/Types/reconciliation';
// @ts-expect-error Every financial application requires a source revision.
const missingRevision: ReconciliationApply = { acknowledged_keys: ['products:1:subtotal_clp'] };
// @ts-expect-error Review keys must reference differences, never numeric row IDs.
const numericKeys: ReconciliationApply = { expected_revision: 1, acknowledged_keys: [1] };
declare const comparison: CostComparison;
// @ts-expect-error HTTP amounts remain decimal text.
const numberAmount: number = comparison.differences.data[0]!.before;
// @ts-expect-error A blocked legacy calculation has no computed after summary.
const total: string = comparison.after.total;
void missingRevision; void numericKeys; void numberAmount; void total;
