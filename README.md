# Migración de SANTIAGO AI PREDICTOR PRO a Supabase + Chat de IA (Groq)

## 1. Habilitar Postgres en tu XAMPP
Tu PHP local viene con el driver de MySQL activo, pero para hablar con Supabase (que es Postgres) necesitas el driver `pgsql`:

1. Abre `php.ini` (en XAMPP: `xampp/php/php.ini`).
2. Busca y descomenta (quita el `;` del inicio) estas líneas:
   ```
   extension=pgsql
   extension=pdo_pgsql
   ```
3. Reinicia Apache desde el panel de XAMPP.

## 2. Crear el proyecto en Supabase
1. Crea un proyecto en https://supabase.com.
2. Ve a **SQL Editor** y pega el contenido de `config/schema.sql`, luego ejecútalo. Esto crea `estadisticas_reales` (tu tabla real, la que usa el código) y `chat_historial`. Este archivo reemplaza a `config/estadisticas_futbol.sql`, que era el dump de otra tabla que el código ya no usa.
3. Ve a **Settings → Database → Connection string** y copia host, puerto, base de datos, usuario y contraseña.

## 3. Configurar variables de entorno
1. Copia `.env.example` a `.env` en la raíz del proyecto.
2. Llena los datos de Supabase que copiaste en el paso 2.
3. **Importante:** el token de football-data.org que tenías (`8593f518...`) quedó expuesto en el código que me pasaste. Ve a tu cuenta en football-data.org, revócalo y genera uno nuevo, y ponlo en `.env`.
4. Crea una cuenta gratis en https://console.groq.com, genera una API key y ponla en `.env` como `GROQ_API_KEY`.

## 4. Reemplazar tu carpeta actual por esta
Esta carpeta ya viene lista para usar — copia todo su contenido sobre tu proyecto en XAMPP (`htdocs/tu-proyecto`):
- `config/database.php` y `config/env.php` → nueva conexión a Supabase.
- `core/api_fetch.php` → ya usa `ON CONFLICT` (Postgres) y lee el token desde `.env`.
- `core/chat_api.php` → nuevo, el endpoint que habla con Groq.
- `includes/chat_widget.php` → nuevo, la burbuja de chat.
- `index.php` → ya incluye `chat_widget.php` antes de `</body>`.
- `includes/nav.php` y `includes/footer.php` → sin cambios, tal cual los tenías (el botón "🔄 SYNC" ya apunta a `core/api_fetch.php`).
- `core/engine.cpp` → lo dejé sin tocar, no vi que `index.php` lo esté llamando con `exec()`, así que de momento no hace nada en la app. Dime si lo estás usando desde otro lado y lo conecto.
- No incluí `config/jdk-26_windows-x64_bin.exe` ni `core/engine.exe` porque son binarios (~190 MB y ~2.7 MB) que no forman parte del código del sitio — no había razón para moverlos.

## 5. Sincronizar datos de la temporada actual
Entra a `core/api_fetch.php` en el navegador (o dale clic al botón SYNC del nav). Va a traer los standings actuales de cada liga desde football-data.org y guardarlos en Supabase.

## ⚠️ Algo que debes saber sobre "datos reales"
Revisando tu `api_fetch.php` original, noté que **córners, tarjetas y atajadas del portero nunca vinieron de la API real** — están generados con `rand()` (número aleatorio) porque el plan gratuito de football-data.org no incluye esas estadísticas en `/standings`. Lo dejé igual para no romper nada, pero si quieres que esos datos sean reales de verdad, hay dos caminos:
- Usar una API que sí los entregue (ej. API-Football de API-Sports, tiene plan gratis limitado).
- Aceptar que esos valores son "simulados" y dejarlo como una función de entretenimiento, no de predicción real.

Dime cuál prefieres y lo ajustamos.

## Archivos de este paquete
```
config/database.php   → conexión PDO a Supabase (Postgres)
config/env.php         → carga variables desde .env
database/schema.sql    → esquema real de la tabla + tabla de historial del chat
core/api_fetch.php     → sincroniza standings desde football-data.org (ahora con UPSERT de Postgres)
core/chat_api.php      → endpoint del chat de IA (Groq)
includes/chat_widget.php → burbuja de chat para pegar en index.php
.env.example            → plantilla de variables de entorno
```
