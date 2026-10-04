<nav style="background: #161a1e; padding: 0; border-bottom: 2px solid #00ff00; font-family: 'Segoe UI', sans-serif; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.5); width: 100%; box-sizing: border-box;">
    <div class="nav-container">
        
        <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 10px;">
            <div style="background: #00ff00; color: #000; font-weight: 900; padding: 5px 10px; border-radius: 4px; font-size: 18px;">DS</div>
            <div class="logo-text" style="color: white; font-weight: 800; font-size: 20px; letter-spacing: -0.5px;">
                AI <span style="color: #00ff00;">SANTIAGO</span>
            </div>
        </a>

        <div class="nav-links-group">
            
            <div class="nav-item">
                <span class="nav-link <?= ($_GET['liga']??'') == 'WC' ? 'active' : '' ?>">🌍 SELECCIONES</span>
                <div class="dropdown">
                    <a href="index.php?liga=WC">🏆 Copa del Mundo</a>
                    <a href="index.php?liga=EC">🇪🇺 Eurocopa</a>
                    <a href="index.php?liga=HL_LIB">🏆 Copa Libertadores</a>
                    <a href="index.php?liga=HL_SUD">🥈 Copa Sudamericana</a>
                </div>
            </div>

            <div class="nav-item">
                <span class="nav-link">🇪🇺 EUROPA</span>
                <div class="dropdown">
                    <div style="padding: 10px; color: #00ff00; font-size: 10px; font-weight: bold; border-bottom: 1px solid #333;">TOP LIGAS</div>
                    <a href="index.php?liga=PL">🏴󠁧󠁢󠁥󠁮󠁧󠁿 Premier League</a>
                    <a href="index.php?liga=PD">🇪🇸 La Liga</a>
                    <a href="index.php?liga=BL1">🇩🇪 Bundesliga</a>
                    <a href="index.php?liga=SA">🇮🇹 Serie A</a>
                    <a href="index.php?liga=FL1">🇫🇷 Ligue 1</a>
                    <a href="index.php?liga=PPL">🇵🇹 Primeira Liga (Por)</a>
                    <a href="index.php?liga=DED">🇳🇱 Eredivisie (Hol)</a>
                    <a href="index.php?liga=ELC">🏴󠁧󠁢󠁥󠁮󠁧󠁿 Championship</a>
                    <a href="index.php?liga=CL">✨ Champions League</a>
                </div>
            </div>

            <div class="nav-item">
                <span class="nav-link">🌎 LATINOAMÉRICA</span>
                <div class="dropdown">
                    <div style="padding: 10px; color: #00ff00; font-size: 10px; font-weight: bold; border-bottom: 1px solid #333;">LIGAS LOCALES</div>
                    <a href="index.php?liga=HL_COL">🇨🇴 Liga BetPlay (Primera A)</a>
                    <a href="index.php?liga=BSA">🇧🇷 Brasileirão</a>
                    <a href="index.php?liga=HL_ARG">🇦🇷 Liga Profesional Argentina</a>
                    <a href="index.php?liga=HL_URU">🇺🇾 Primera Uruguay</a>
                    <a href="index.php?liga=HL_CHI">🇨🇱 Primera Chile</a>
                    <a href="index.php?liga=HL_PAR">🇵🇾 División Profesional Paraguay</a>
                    <a href="index.php?liga=HL_PER">🇵🇪 Primera Perú</a>
                    <a href="index.php?liga=HL_ECU">🇪🇨 Liga Pro Ecuador</a>
                    <a href="index.php?liga=HL_MEX">🇲🇽 Liga MX</a>
                </div>
            </div>

            <div class="nav-item">
                <span class="nav-link">🌐 OTRAS</span>
                <div class="dropdown">
                    <a href="index.php?liga=HL_MLS">🇺🇸 MLS</a>
                    <a href="index.php?liga=HL_SAU">🇸🇦 Pro League Arabia Saudita</a>
                </div>
            </div>

        </div>

        <div class="nav-actions">
            <div class="api-status">
                <div style="color: white; font-size: 11px; font-weight: bold;">STATUS API</div>
                <div style="color: #00ff00; font-size: 10px;">● ONLINE</div>
            </div>
            <a href="core/api_fetch.php" class="sync-button">🔄 SYNC EUROPA</a>
            <a href="core/api_fetch_highlightly.php" class="sync-button" style="border-color:#f1c40f; color:#f1c40f;">🔄 SYNC LATAM</a>
        </div>
    </div>

    <style>
        .nav-container { max-width: 1300px; margin: auto; display: flex; justify-content: space-between; align-items: center; height: 70px; padding: 0 25px; box-sizing: border-box; }
        .nav-links-group { display: flex; gap: 5px; height: 100%; }
        .nav-actions { display: flex; align-items: center; gap: 15px; }
        .sync-button { background: #2b2f36; border: 1px solid #00ff00; color: #00ff00; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: bold; transition: 0.3s; white-space: nowrap; }
        .sync-button:hover { background: #00ff00; color: #000; }
        
        .nav-item { position: relative; display: flex; align-items: center; }
        .nav-link { color: #848e9c; text-decoration: none; font-size: 13px; font-weight: 700; padding: 0 20px; height: 100%; display: flex; align-items: center; cursor: pointer; transition: 0.3s; border-bottom: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { color: #00ff00; background: rgba(0, 255, 0, 0.05); border-bottom: 3px solid #00ff00; }
        
        .dropdown { display: none; position: absolute; top: 70px; left: 0; background: #1c2127; min-width: 260px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #333; border-radius: 0 0 10px 10px; z-index: 1001; }
        .nav-item:hover .dropdown { display: block; animation: fadeIn 0.2s ease-out; }
        .dropdown a { color: white; padding: 12px 20px; text-decoration: none; display: block; font-size: 13px; transition: 0.2s; border-bottom: 1px solid #222; }
        .dropdown a:hover { background: #2b2f36; color: #00ff00; padding-left: 25px; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- RESPONSIVE --- */
        @media (max-width: 950px) {
            .logo-text, .api-status { display: none; }
            .nav-container { height: 60px; padding: 0 10px; }
            .nav-link { padding: 0 10px; font-size: 11px; }
            .sync-button { padding: 5px 8px; font-size: 10px; }
            .dropdown { top: 60px; min-width: 220px; }
        }
    </style>
</nav>