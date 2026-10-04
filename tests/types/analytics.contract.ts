import type { AnalyticsProps } from '../../resources/js/Types/analytics';
declare const analytics: AnalyticsProps;
// @ts-expect-error HTTP money stays exact text even beyond Number.MAX_SAFE_INTEGER.
analytics.metrics.total = 0.1 + 0.2;
// @ts-expect-error A pending total projection cannot be treated as an available amount.
const assumedProfit: string = analytics.projection.profit;
// @ts-expect-error Financial read data never contains private attachment paths.
analytics.imports.data[0].storage_path;
// @ts-expect-error Category totals cannot be replaced by floating-point values.
analytics.categories.data[0].total = 0.1 + 0.2;
void assumedProfit;
