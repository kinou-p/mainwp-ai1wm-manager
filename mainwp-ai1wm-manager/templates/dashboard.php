<div class="ai1wm-wrap">

    <!-- Header -->
    <div class="ai1wm-header">
        <div class="ai1wm-header-left">
            <div class="ai1wm-header-icon">
                <span class="material-icons-round">cloud_sync</span>
            </div>
            <div>
                <h1>
                    <?php esc_html_e('AI1WM Backup Manager', 'mainwp-ai1wm-manager'); ?>
                </h1>
                <div class="ai1wm-version">Version
                    <?php echo esc_html(MAINWP_AI1WM_MANAGER_VERSION); ?>
                </div>
            </div>
        </div>

        <?php if (!empty($sites)): ?>
            <div class="ai1wm-toolbar">
                <span class="ai1wm-selected-count" id="ai1wm-selected-count">
                    <span class="count-num">0</span> sélectionné(s)
                </span>
                <button class="ai1wm-btn ai1wm-btn-outline" id="ai1wm-refresh-all">
                    <span class="material-icons-round">refresh</span>
                    Rafraîchir tout
                </button>
                <button class="ai1wm-btn ai1wm-btn-secondary" id="ai1wm-bulk-download" disabled>
                    <span class="material-icons-round">download</span>
                    Télécharger derniers backups
                </button>
                <button class="ai1wm-btn ai1wm-btn-primary" id="ai1wm-bulk-backup" disabled>
                    <span class="material-icons-round">backup</span>
                    Créer backups
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stats Cards -->
    <?php if (!empty($sites)): ?>
        <div class="ai1wm-stats">
            <div class="ai1wm-stat-card glass-panel">
                <div>
                    <div class="stat-label">Total Sites</div>
                    <div class="stat-value" id="ai1wm-stat-sites">
                        <?php echo count($sites); ?>
                    </div>
                </div>
                <div class="ai1wm-stat-icon">
                    <span class="material-icons-round">language</span>
                </div>
            </div>
            <div class="ai1wm-stat-card glass-panel">
                <div>
                    <div class="stat-label">Total Backups</div>
                    <div class="stat-value" id="ai1wm-stat-backups">–</div>
                </div>
                <div class="ai1wm-stat-icon">
                    <span class="material-icons-round">inventory_2</span>
                </div>
            </div>
            <div class="ai1wm-stat-card glass-panel">
                <div>
                    <div class="stat-label">Dernier Backup</div>
                    <div class="stat-value" id="ai1wm-stat-last" style="font-size:16px;">–</div>
                </div>
                <div class="ai1wm-stat-icon green">
                    <span class="material-icons-round">schedule</span>
                </div>
            </div>
            <div class="ai1wm-stat-card glass-panel">
                <div>
                    <div class="stat-label">Taille Totale</div>
                    <div class="stat-value" id="ai1wm-stat-total-size" style="font-size:18px;">–</div>
                </div>
                <div class="ai1wm-stat-icon blue">
                    <span class="material-icons-round">sd_card</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bulk Progress -->
    <div class="ai1wm-bulk-progress glass-panel" id="ai1wm-bulk-progress">
        <h4 id="ai1wm-bulk-title">Opération en cours…</h4>
        <div class="ai1wm-progress-bar">
            <div class="ai1wm-progress-fill" id="ai1wm-progress-fill"></div>
        </div>
        <span class="ai1wm-progress-text" id="ai1wm-progress-text">0 / 0</span>
    </div>

    <?php if (empty($sites)): ?>
        <div class="ai1wm-empty glass-panel">
            <span class="material-icons-round">cloud_off</span>
            <?php esc_html_e('Aucun site enfant trouvé.', 'mainwp-ai1wm-manager'); ?>
        </div>
    <?php else: ?>
        <div class="ai1wm-table-wrap glass-panel">
            <table class="ai1wm-table" id="ai1wm-sites-table">
                <thead>
                    <tr>
                        <th class="cb-col"><input type="checkbox" id="ai1wm-select-all" title="Tout sélectionner"></th>
                        <th class="ai1wm-sortable" data-sort="name">
                            <?php esc_html_e('Site Web', 'mainwp-ai1wm-manager'); ?>
                            <span class="material-icons-round sort-icon">unfold_more</span>
                        </th>
                        <th class="ai1wm-sortable" data-sort="url">
                            <?php esc_html_e('URL', 'mainwp-ai1wm-manager'); ?>
                            <span class="material-icons-round sort-icon">unfold_more</span>
                        </th>
                        <th class="ai1wm-sortable" data-sort="backups" style="text-align:center;">
                            <?php esc_html_e('Backups', 'mainwp-ai1wm-manager'); ?>
                            <span class="material-icons-round sort-icon">unfold_more</span>
                        </th>
                        <th class="ai1wm-sortable" data-sort="last-backup" style="width:180px;">
                            <?php esc_html_e('Dernier Backup', 'mainwp-ai1wm-manager'); ?>
                            <span class="material-icons-round sort-icon">unfold_more</span>
                        </th>
                        <th style="width:180px;text-align:right;">
                            <?php esc_html_e('Actions', 'mainwp-ai1wm-manager'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $idx => $site):
                        $favicon_url = 'https://www.google.com/s2/favicons?domain=' . urlencode($site['url']) . '&sz=64';
                        ?>
                        <tr data-site-id="<?php echo esc_attr($site['id']); ?>" class="ai1wm-site-row">
                            <td class="cb-col">
                                <input type="checkbox" class="ai1wm-site-cb" value="<?php echo esc_attr($site['id']); ?>">
                            </td>
                            <td>
                                <div class="site-info">
                                    <div class="site-avatar">
                                        <img src="<?php echo esc_url($favicon_url); ?>" alt=""
                                            style="width:100%;height:100%;object-fit:cover;border-radius:6px;">
                                    </div>
                                    <div>
                                        <div class="site-name">
                                            <?php echo esc_html($site['name']); ?>
                                        </div>
                                        <div class="site-status">Connecté</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($site['url']); ?>" class="site-url" target="_blank">
                                    <?php echo esc_html(preg_replace('#^https?://#', '', rtrim($site['url'], '/'))); ?>
                                </a>
                            </td>
                            <td style="text-align:center;">
                                <span class="ai1wm-toggle" data-site-id="<?php echo esc_attr($site['id']); ?>">
                                    <span class="ai1wm-backup-count"
                                        data-site-id="<?php echo esc_attr($site['id']); ?>">–</span>
                                    <span class="material-icons-round">expand_more</span>
                                </span>
                            </td>
                            <td>
                                <div class="ai1wm-last-backup" data-site-id="<?php echo esc_attr($site['id']); ?>" style="color:var(--ai-text-muted);font-size:13px;">
                                    <span class="material-icons-round" style="font-size:16px;vertical-align:middle;margin-right:4px;">schedule</span>
                                    <span class="last-backup-date">–</span>
                                </div>
                            </td>
                            <td>
                                <div class="ai1wm-actions">
                                    <button class="ai1wm-btn ai1wm-btn-icon-primary ai1wm-btn-create"
                                        data-site-id="<?php echo esc_attr($site['id']); ?>" title="Créer un backup">
                                        <span class="material-icons-round">backup</span>
                                    </button>
                                    <button class="ai1wm-btn ai1wm-btn-ghost ai1wm-btn-list"
                                        data-site-id="<?php echo esc_attr($site['id']); ?>" title="Rafraîchir les backups">
                                        <span class="material-icons-round">refresh</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr class="ai1wm-backups-row" data-site-id="<?php echo esc_attr($site['id']); ?>" style="display:none;">
                            <td colspan="6">
                                <div class="ai1wm-backups-container">
                                    <div class="ai1wm-backups-inner">
                                        <h4>
                                            <span class="material-icons-round" style="font-size:16px;">folder_open</span>
                                            <?php printf(esc_html__('Fichiers .wpress — %s', 'mainwp-ai1wm-manager'), esc_html($site['name'])); ?>
                                        </h4>
                                        <div class="ai1wm-backups-content">
                                            <span class="ai1wm-spinner ai1wm-spinner-dark"></span> Chargement…
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Logs Section -->
    <div class="glass-panel" style="margin-top: 32px; padding: 24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;font-size:16px;color:#fff;display:flex;align-items:center;gap:8px;">
                <span class="material-icons-round" style="color:var(--ai-text-muted);">history</span>
                Journal d'activité
            </h3>
            <div style="display:flex;gap:8px;">
                <button class="ai1wm-btn ai1wm-btn-ghost ai1wm-btn-sm" id="ai1wm-refresh-logs"
                    title="Rafraîchir les logs">
                    <span class="material-icons-round">refresh</span>
                </button>
                <button class="ai1wm-btn ai1wm-btn-ghost ai1wm-btn-sm" id="ai1wm-clear-logs"
                    title="Effacer l'historique">
                    <span class="material-icons-round">delete_sweep</span>
                </button>
            </div>
        </div>

        <div id="ai1wm-logs-container"
            style="max-height: 300px; overflow-y: auto; background: rgba(15,23,42,0.6); border-radius: 8px; padding: 0;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <tbody id="ai1wm-logs-body">
                    <tr>
                        <td style="padding:20px;text-align:center;color:var(--ai-text-dim);">Chargement...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>