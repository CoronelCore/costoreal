import type { AxiosStatic } from 'axios';
import type { Config } from '../../../vendor/tightenco/ziggy';
import type { Actor, CompanySummary } from './financial';

export interface SharedPageProps {
    auth: { user: (Actor & { email: string }) | null; activeCompany: CompanySummary | null };
    company: { active: CompanySummary | null; activeId: number | null; activeName: string; userCompanies: CompanySummary[] };
    flash: { success: string | null; error: string | null; info: string | null };
    ziggy: Config & { location: string };
}

declare module '@inertiajs/core' {
    interface PageProps extends SharedPageProps {}
}

declare global {
    interface Window { axios: AxiosStatic }
}
