import type { AxiosStatic } from 'axios';
import type { Config } from '../../../vendor/tightenco/ziggy';
import type { Actor, CompanySummary } from './financial';

export interface SharedPageProps {
    auth: { user: (Actor & { email: string }) | null; activeCompany: CompanySummary | null };
    company: { active: CompanySummary | null; activeId: number | null; activeName: string; userCompanies: CompanySummary[] };
    ziggy: Config & { location: string };
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedPageProps;
        flashDataType: { success?: string; error?: string; info?: string };
    }
}

declare global {
    interface Window { axios: AxiosStatic }
}
