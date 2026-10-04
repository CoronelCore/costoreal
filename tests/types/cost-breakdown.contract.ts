import type { AllocationTrace, CostBreakdown, ImportShowProps } from '../../resources/js/Types/financial';
declare const trace: AllocationTrace;
// @ts-expect-error An allocation receives at most one residual peso.
const incorrectAward: AllocationTrace = { ...trace, residual_award: 2 };
declare const result: CostBreakdown;
// @ts-expect-error Original revisions may have no recorded residual trace.
const assumedFloor: string = result.allocations.data[0].trace.floor;
declare const page: ImportShowProps;
// @ts-expect-error Full cost results are deliberately absent from the initial page.
page.costBreakdown.allocations;
// @ts-expect-error Historic money remains exact decimal text.
const roundedPurchase: string = Number(result.line.purchase);
void [incorrectAward, assumedFloor, roundedPurchase];
