import test from 'node:test';
import assert from 'node:assert/strict';
import { currentScreen, screenLabel, deviceLabel } from '../../resources/js/Utils/screens.ts';

test('a comment records the kind of screen and a known tab, nothing else', () => {
    assert.equal(currentScreen('Trips/Show', '#gastos'), 'Trips/Show#gastos');
    assert.equal(currentScreen('Trips/Index', ''), 'Trips/Index');
    assert.equal(currentScreen('Trips/Show', '#Viaje a Brasil'), 'Trips/Show');
    assert.equal(currentScreen('Trips/Show', '#otra-cosa'), 'Trips/Show');
});

test('the owner reads the screen and device in plain words', () => {
    assert.equal(screenLabel('Trips/Show#precio-venta'), 'Una importación · Precios');
    assert.equal(screenLabel('Analytics/Index'), 'Analítica');
    assert.equal(screenLabel('Futura/Pantalla'), 'Futura/Pantalla');
    assert.equal(screenLabel(null), 'Pantalla no indicada');
    assert.equal(deviceLabel(390), 'Celular');
    assert.equal(deviceLabel(900), 'Tableta');
    assert.equal(deviceLabel(1440), 'Computador');
    assert.equal(deviceLabel(null), '');
});
