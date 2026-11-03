// Comments carry the kind of screen they were written on, never the name of an import or its numbers.
const SCREENS: Record<string, string> = {
    'Trips/Index': 'Control de importaciones',
    'Trips/Show': 'Una importación',
    'Trips/Create': 'Nueva importación',
    'Trips/Edit': 'Editar importación',
    'Trips/Trash': 'Papelera',
    'Analytics/Index': 'Analítica',
    'Companies/Index': 'Empresas',
    'Companies/Show': 'Una empresa',
    'Companies/Create': 'Nueva empresa',
    'Companies/Edit': 'Editar empresa',
    'Categories/Index': 'Categorías',
    'Categories/Create': 'Nueva categoría',
    'Categories/Edit': 'Editar categoría',
    'Profile/Edit': 'Perfil',
};
const TABS: Record<string, string> = {
    resumen: 'Resumen',
    productos: 'Hoja de productos',
    gastos: 'Gastos',
    'precio-venta': 'Precios',
    documentos: 'Documentos',
    escenarios: 'Escenarios',
    revision: 'Revisión',
    historial: 'Historial',
    config: 'Configurar',
};

/** "Trips/Show" and "#gastos" become "Trips/Show#gastos"; any other hash is dropped. */
export function currentScreen(component: string, hash: string): string {
    const tab = hash.replace(/^#/, '');
    return /^[a-z-]+$/.test(tab) && TABS[tab] ? `${component}#${tab}` : component;
}

export function screenLabel(screen: string | null | undefined): string {
    if (!screen) return 'Pantalla no indicada';
    const [component, tab] = screen.split('#');
    const name = SCREENS[component] ?? component;
    return tab && TABS[tab] ? `${name} · ${TABS[tab]}` : name;
}

export function deviceLabel(width: number | null | undefined): string {
    if (!width) return '';
    return width < 768 ? 'Celular' : width < 1100 ? 'Tableta' : 'Computador';
}
