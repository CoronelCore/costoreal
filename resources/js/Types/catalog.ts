import type { ImportListItem, Pagination } from "./financial";
export interface CatalogRecord {
    id: number;
    nombre: string;
    icono: string | null;
    color: string | null;
    rut?: string | null;
    descripcion?: string | null;
    activa?: boolean;
    trips_count?: number;
    expenses_count?: number;
    editable?: boolean;
    can_delete?: boolean;
}
export interface CatalogMutation {
    nombre: string;
    icono: string;
    color: string;
    rut: string;
    descripcion: string;
    activa: boolean;
}
export interface CompanyPage {
    companyRecord: CatalogRecord;
    trips: Pagination<ImportListItem>;
    metrics: { count: number; active: number; total: string; average: string };
}
