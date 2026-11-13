(function () {
    function normalizar(texto) {
        return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    document.querySelectorAll('.tabla-buscar').forEach(function (campo) {
        const tabla = document.getElementById(campo.dataset.tabla);
        if (!tabla) {
            return;
        }
        campo.addEventListener('input', function () {
            const q = normalizar(campo.value.trim());
            tabla.querySelectorAll('tbody tr').forEach(function (fila) {
                fila.hidden = q !== '' && normalizar(fila.textContent).indexOf(q) === -1;
            });
        });
    });

    const tabla = document.getElementById('tabla-posiciones');
    if (!tabla || !tabla.dataset.liga) {
        return;
    }

    const estado = document.getElementById('tabla-estado');
    const liga = tabla.dataset.liga;
    const cuerpo = tabla.querySelector('tbody');

    function valorTexto(campo, fila) {
        if (campo === 'dif') {
            const dif = fila.gf - fila.gc;
            return (dif > 0 ? '+' : '') + dif;
        }
        const valor = fila[campo];
        return valor === null || valor === undefined ? '-' : String(valor);
    }

    function marcar(celda) {
        celda.classList.add('celda-cambio');
        setTimeout(function () {
            celda.classList.remove('celda-cambio');
        }, 1800);
    }

    function aplicar(datos) {
        const campos = ['pos', 'pj', 'g', 'e', 'p', 'gf', 'gc', 'dif', 'pts'];
        const filasPorEquipo = new Map();
        cuerpo.querySelectorAll('tr').forEach(function (tr) {
            filasPorEquipo.set(tr.dataset.equipo, tr);
        });

        datos.filas.forEach(function (fila) {
            const tr = filasPorEquipo.get(fila.equipo);
            if (!tr) {
                return;
            }
            campos.forEach(function (campo) {
                const celda = tr.querySelector('[data-campo="' + campo + '"]');
                if (!celda) {
                    return;
                }
                const nuevo = valorTexto(campo, fila);
                if (celda.textContent !== nuevo) {
                    celda.textContent = nuevo;
                    marcar(celda);
                }
            });
        });

        const ordenadas = Array.from(cuerpo.querySelectorAll('tr')).sort(function (a, b) {
            const pa = parseInt(a.querySelector('[data-campo="pos"]').textContent, 10);
            const pb = parseInt(b.querySelector('[data-campo="pos"]').textContent, 10);
            return (isNaN(pa) ? 999 : pa) - (isNaN(pb) ? 999 : pb);
        });
        const total = ordenadas.length;
        ordenadas.forEach(function (tr, i) {
            cuerpo.appendChild(tr);
            tr.classList.toggle('zona-alta', i < 4);
            tr.classList.toggle('zona-baja', i >= total - 3 && total > 6);
        });

        if (estado && datos.actualizado) {
            estado.textContent = 'Datos de la tabla actualizados el ' + datos.actualizado + ' (hora de Colombia)';
        }
    }

    async function refrescar() {
        if (document.hidden) {
            return;
        }
        try {
            const res = await fetch('tabla_json.php?liga=' + encodeURIComponent(liga), { cache: 'no-store' });
            const datos = JSON.parse(await res.text());
            if (!datos.error) {
                aplicar(datos);
            }
        } catch (e) {
            if (estado) {
                estado.textContent = 'No se pudo actualizar la tabla ahora; se muestra la última versión cargada.';
            }
        }
    }

    setInterval(refrescar, 300000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            refrescar();
        }
    });
})();