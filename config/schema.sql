-- Esquema para Supabase (Postgres)
-- Reemplaza a estadisticas_futbol.sql (ese dump correspondía a otra tabla
-- "partidos_stats" que el código PHP ya no usa; la tabla real es esta).

create table if not exists public.estadisticas_reales (
    id                  bigserial primary key,
    equipo_nombre       text not null,
    liga_id             text not null,
    logo_url            text,
    posicion_tabla      int,
    partidos_jugados    int default 0,
    goles_favor         int default 0,
    goles_contra        int default 0,
    tarjetas_rojas      int default 0,
    penaltis_anotados   int default 0,
    corners_total       int default 0,
    tarjetas_amarillas  int default 0,
    atajadas_portero    int default 0,
    posesion_media      numeric(5,2),
    actualizado_en      timestamptz not null default now(),
    constraint equipo_liga_unico unique (equipo_nombre, liga_id)
);

create index if not exists idx_estadisticas_liga on public.estadisticas_reales (liga_id);

-- RLS: la app se conecta directo por Postgres con el usuario de servicio,
-- así que esto no la bloquea a ella; protege el dato si algún día expones
-- la tabla por la API REST/anon key de Supabase desde el navegador.
alter table public.estadisticas_reales enable row level security;

drop policy if exists "lectura_publica" on public.estadisticas_reales;
create policy "lectura_publica"
    on public.estadisticas_reales
    for select
    using (true);

-- Opcional: guarda un historial del chat de IA (útil para revisar uso/costos)
create table if not exists public.chat_historial (
    id          bigserial primary key,
    mensaje     text not null,
    respuesta   text not null,
    local       text,
    visitante   text,
    creado_en   timestamptz not null default now()
);

alter table public.chat_historial enable row level security;
drop policy if exists "sin_acceso_publico" on public.chat_historial;
create policy "sin_acceso_publico"
    on public.chat_historial
    for select
    using (false);
