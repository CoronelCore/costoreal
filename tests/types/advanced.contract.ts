import type { BulkApplyMutation, BulkPreview, BulkPreviewMutation, PricingMutation, ScenarioPreviewMutation, ScenarioSaveMutation } from '../../resources/js/Types/advanced';

declare const pricing: PricingMutation;
// @ts-expect-error Price policies require the selected product IDs.
const missingSelection: PricingMutation = { method: 'margin', target: '20', commission: '0', quantum: 1, expected_revision: 2 };
// @ts-expect-error Decimal text must not become an imprecise JSON number.
const numericTarget: PricingMutation = { ...pricing, target: 20 };
declare const bulk: BulkPreviewMutation;
// @ts-expect-error Applying a preview requires its idempotency key.
const duplicateRisk: BulkApplyMutation = bulk;
// @ts-expect-error File and pasted text are mutually exclusive sources.
const ambiguousSource: BulkPreviewMutation = { ...bulk, file: new File([], 'a.csv'), source_text: 'a' };
declare const scenario: Omit<ScenarioPreviewMutation, 'expected_revision'>;
// @ts-expect-error Simulations require the revision that was read.
const missingRevision: ScenarioPreviewMutation = scenario;
declare const preview: ScenarioPreviewMutation;
// @ts-expect-error Saved scenarios require a name and an idempotency key.
const unnamedScenario: ScenarioSaveMutation = preview;
declare const result: BulkPreview;
// @ts-expect-error Invalid previews have no computed totals.
const assumedTotal: string = result.totals.total;
void [missingSelection, numericTarget, duplicateRisk, ambiguousSource, missingRevision, unnamedScenario, assumedTotal];
