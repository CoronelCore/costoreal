import type { AxiosStatic } from 'axios';
import type { Config } from '../../../vendor/tightenco/ziggy';
import type { Actor, CompanySummary } from './financial';

/** A one-time link the owner sends by chat: shown once, never kept in browser history. */
export interface SharedLink { kind: 'password' | 'invitation'; name: string; url: string; hours: number }

export interface SharedPageProps {
    auth: { user: (Actor & { email: string; is_owner: boolean }) | null; activeCompany: CompanySummary | null };
    company: { active: CompanySummary | null; activeId: number | null; activeName: string; userCompanies: CompanySummary[] };
    beta: { owner: string | null } | null;
    ziggy: Config & { location: string };
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedPageProps;
        flashDataType: { success?: string; error?: string; info?: string; link?: SharedLink };
    }
}

declare global {
    interface Window { axios: AxiosStatic }
}
