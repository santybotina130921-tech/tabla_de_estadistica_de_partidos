(function () {
    const app = document.getElementById('hoy-app');
    if (!app) {
        return;
    }

    const urlDatos = app.dataset.url;
    const esHoy = app.dataset.dia === 'hoy';
    const buscador = document.getElementById('hoy-buscar');
    const nota = document.getElementById('hoy-nota');
    const avisos = document.getElementById('hoy-avisos');
    const claveFavoritos = 'santiago_favoritos';
    const etiquetasEstado = { vivo: 'EN VIVO', final: 'FINAL', programado: 'PROGRAMADO', aplazado: 'APLAZADO' };

    let ultimosDatos = null;
    let firma = '';
    let reintentos = 0;
    let ultimaCarga = 0;
    let temporizador = null;
    const filas = new Map();

    function crear(etiqueta, clase, texto) {
        const el = document.createElement(etiqueta);
        if (clase) {
            el.className = clase;
        }
        if (texto !== undefined) {
            el.textContent = texto;
        }
        return el;
    }

    function leerFavoritos() {
        try {
            return JSON.parse(localStorage.getItem(claveFavoritos)) || [];
        } catch (e) {
            return [];
        }
    }

    function guardarFavoritos(lista) {
        try {
            localStorage.setItem(claveFavoritos, JSON.stringify(lista));
        } catch (e) {
            return;
        }
    }

    function textoMarcador(p) {
        const conMarcador = (p.estado === 'vivo' || p.estado === 'final') && p.gl !== null && p.gv !== null;
        return conMarcador ? p.gl + ' - ' + p.gv : 'vs';
    }

    function textoEstado(p) {
        const minuto = (p.estado === 'vivo' && p.minuto) ? ' ' + parseInt(p.minuto, 10) + "'" : '';
        return etiquetasEstado[p.estado] + minuto;
    }

    function botonFavorito(nombre) {
        const boton = crear('button', 'fav-btn', '☆');
        boton.type = 'button';
        boton.dataset.equipo = nombre;
        boton.setAttribute('aria-label', 'Marcar ' + nombre + ' como favorito');
        return boton;
    }

    function equipo(nombre, logo) {
        const span = crear('span', 'hoy-equipo');
        span.appendChild(botonFavorito(nombre));
        if (logo) {
            const img = document.createElement('img');
            img.src = logo;
            img.alt = '';
            img.width = 22;
            img.height = 22;
            img.loading = 'lazy';
            img.decoding = 'async';
            span.appendChild(img);
        }
        span.appendChild(crear('span', 'hoy-nombre', nombre));
        return span;
    }

    function fila(p, titulo) {
        const f = crear('div', 'hoy-partido');
        f.dataset.k = p.k;
        f.dataset.texto = (p.local + ' ' + p.visitante + ' ' + titulo).toLowerCase();

        f.appendChild(crear('div', 'hoy-hora', p.hora));

        const equipos = crear('div', 'hoy-equipos');
        equipos.appendChild(equipo(p.local, p.local_logo));
        equipos.appendChild(crear('span', 'hoy-marcador', textoMarcador(p)));
        equipos.appendChild(equipo(p.visitante, p.visitante_logo));
        f.appendChild(equipos);

        const lado = crear('div', 'hoy-lado');
        lado.appendChild(crear('span', 'hoy-estado estado-' + p.estado, textoEstado(p)));

        const enlaces = crear('div', 'hoy-enlaces');
        const analizar = crear('a', 'hoy-analizar', 'Analizar →');
        analizar.href = p.url;
        enlaces.appendChild(analizar);
        if (p.resumen) {
            const resumen = crear('a', 'hoy-analizar', 'Resumen');
            resumen.href = p.resumen;
            enlaces.appendChild(resumen);
        }
        lado.appendChild(enlaces);
        f.appendChild(lado);

        filas.set(p.k, f);
        return f;
    }

    function calcularFirma(datos, favoritos) {
        const claves = datos.grupos.map(function (g) {
            return g.codigo + ':' + g.partidos.map(function (p) { return p.k; }).join(',');
        });
        return claves.join(';') + '|' + favoritos.join(',');
    }

    function construir(datos) {
        const favoritos = leerFavoritos();
        filas.clear();
        app.textContent = '';

        if (!datos.grupos.length) {
            app.appendChild(crear('div', 'hist-vacio', 'No hay partidos de las ligas que seguimos para este día.'));
            return;
        }

        const esFavorito = function (p) {
            return favoritos.indexOf(p.local) !== -1 || favoritos.indexOf(p.visitante) !== -1;
        };

        const delosFavoritos = [];
        datos.grupos.forEach(function (g) {
            g.partidos.forEach(function (p) {
                if (esFavorito(p)) {
                    delosFavoritos.push({ p: p, titulo: g.titulo });
                }
            });
        });

        if (delosFavoritos.length) {
            const bloque = crear('div', 'hoy-liga hoy-liga-fav');
            bloque.dataset.titulo = 'favoritos';
            bloque.appendChild(crear('h3', '', '⭐ Mis favoritos'));
            delosFavoritos.forEach(function (x) {
                bloque.appendChild(fila(x.p, x.titulo));
            });
            app.appendChild(bloque);
        }

        datos.grupos.forEach(function (g) {
            const propios = g.partidos.filter(function (p) { return !esFavorito(p); });
            if (!propios.length) {
                return;
            }
            const bloque = crear('div', 'hoy-liga');
            bloque.dataset.titulo = g.titulo.toLowerCase();
            bloque.appendChild(crear('h3', '', g.titulo));
            propios.forEach(function (p) {
                bloque.appendChild(fila(p, g.titulo));
            });
            app.appendChild(bloque);
        });

        pintarFavoritos();
        filtrar();
    }

    function pintarFavoritos() {
        const favoritos = leerFavoritos();
        app.querySelectorAll('.fav-btn').forEach(function (boton) {
            const activo = favoritos.indexOf(boton.dataset.equipo) !== -1;
            boton.textContent = activo ? '★' : '☆';
            boton.classList.toggle('activo', activo);
        });
    }

    function actualizar(datos) {
        datos.grupos.forEach(function (g) {
            g.partidos.forEach(function (p) {
                const f = filas.get(p.k);
                if (!f) {
                    return;
                }
                const marcador = f.querySelector('.hoy-marcador');
                const nuevo = textoMarcador(p);
                if (marcador.textContent !== nuevo) {
                    marcador.textContent = nuevo;
                    marcador.classList.add('cambio');
                    setTimeout(function () { marcador.classList.remove('cambio'); }, 1600);
                }
                const estado = f.querySelector('.hoy-estado');
                estado.textContent = textoEstado(p);
                estado.className = 'hoy-estado estado-' + p.estado;
            });
        });
    }

    function filtrar() {
        const q = buscador ? buscador.value.trim().toLowerCase() : '';
        let visibles = 0;

        filas.forEach(function (f) {
            const oculta = q !== '' && f.dataset.texto.indexOf(q) === -1;
            f.hidden = oculta;
            if (!oculta) {
                visibles++;
            }
        });

        app.querySelectorAll('.hoy-liga').forEach(function (bloque) {
            const hayVisibles = bloque.querySelector('.hoy-partido:not([hidden])') !== null;
            bloque.hidden = !hayVisibles;
        });

        const previo = app.querySelector('.hoy-sin-resultados');
        if (previo) {
            previo.remove();
        }
        if (q !== '' && filas.size > 0 && visibles === 0) {
            app.appendChild(crear('div', 'hist-vacio hoy-sin-resultados', 'Ningún partido coincide con «' + q + '».'));
        }
    }

    function pintarNota(datos) {
        const partes = [datos.total + ' partidos'];
        if (datos.hayVivo) {
            partes.push('hay partidos en vivo');
        }
        partes.push(datos.edad_min > 0 ? 'actualizado hace ' + datos.edad_min + ' min' : 'actualizado ahora');
        nota.textContent = partes.join(' · ');
    }

    function pintarAvisos(datos, extra) {
        avisos.textContent = '';
        const mensajes = (datos ? datos.avisos.slice() : []);
        if (datos && datos.pendientes > 0) {
            mensajes.unshift('Cargando ligas... faltan ' + datos.pendientes + '. Se actualiza sola.');
        }
        if (extra) {
            mensajes.push(extra);
        }
        mensajes.forEach(function (m) {
            avisos.appendChild(crear('div', 'h2h-aviso', m));
        });
    }

    function mostrar(datos) {
        const nuevaFirma = calcularFirma(datos, leerFavoritos());
        if (nuevaFirma !== firma || !ultimosDatos) {
            firma = nuevaFirma;
            construir(datos);
        } else {
            actualizar(datos);
        }
        ultimosDatos = datos;
        pintarNota(datos);
        pintarAvisos(datos, null);
    }

    function mostrarError(mensaje) {
        if (ultimosDatos) {
            pintarAvisos(ultimosDatos, 'No se pudo actualizar (' + mensaje + '). Se sigue mostrando lo último que cargó.');
            return;
        }
        app.textContent = '';
        const bloque = crear('div', 'h2h-aviso', 'No se pudieron cargar los partidos: ' + mensaje);
        const boton = crear('button', 'hoy-reintentar', 'Reintentar');
        boton.type = 'button';
        boton.addEventListener('click', function () {
            app.textContent = '';
            cargar();
        });
        bloque.appendChild(document.createElement('br'));
        bloque.appendChild(boton);
        app.appendChild(bloque);
    }

    function programar(datos) {
        clearTimeout(temporizador);
        if (datos.pendientes > 0 && reintentos < 12) {
            reintentos++;
            temporizador = setTimeout(cargar, 4000);
            return;
        }
        if (esHoy) {
            temporizador = setTimeout(cargar, datos.hayVivo ? 60000 : 180000);
        }
    }

    async function cargar() {
        const controlador = new AbortController();
        const limite = setTimeout(function () { controlador.abort(); }, 25000);
        try {
            const res = await fetch(urlDatos, { signal: controlador.signal, cache: 'no-store' });
            const texto = await res.text();
            let datos;
            try {
                datos = JSON.parse(texto);
            } catch (e) {
                throw new Error('respuesta no válida, HTTP ' + res.status);
            }
            if (datos.error) {
                throw new Error(datos.error);
            }
            ultimaCarga = Date.now();
            mostrar(datos);
            programar(datos);
        } catch (e) {
            mostrarError(e.name === 'AbortError' ? 'la consulta tardó demasiado' : e.message);
            clearTimeout(temporizador);
            if (esHoy) {
                temporizador = setTimeout(cargar, 60000);
            }
        } finally {
            clearTimeout(limite);
        }
    }

    app.addEventListener('click', function (e) {
        const boton = e.target.closest('.fav-btn');
        if (!boton) {
            return;
        }
        const nombre = boton.dataset.equipo;
        const lista = leerFavoritos();
        const pos = lista.indexOf(nombre);
        if (pos === -1) {
            lista.push(nombre);
        } else {
            lista.splice(pos, 1);
        }
        guardarFavoritos(lista);
        if (ultimosDatos) {
            firma = '';
            mostrar(ultimosDatos);
        }
    });

    if (buscador) {
        buscador.addEventListener('input', filtrar);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && esHoy && Date.now() - ultimaCarga > 60000) {
            cargar();
        }
    });

    cargar();
})();