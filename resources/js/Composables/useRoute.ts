import { inject } from 'vue';
import type { route as ziggyRoute } from '../../../vendor/tightenco/ziggy';

export function useRoute(): typeof ziggyRoute {
    const route = inject<typeof ziggyRoute>('route');
    if (!route) throw new Error('No se ha instalado el proveedor de rutas.');
    return route;
}
