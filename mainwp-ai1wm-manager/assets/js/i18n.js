/**
 * Internationalization for AI1WM Backup Manager
 */
(function() {
    'use strict';

    window.AI1WM_i18n = {
        currentLang: localStorage.getItem('ai1wm_lang') || 'fr',

        translations: {
            fr: {
                // Header
                'ai1wm_backup_manager': 'AI1WM Backup Manager',
                'version': 'Version',
                'selected': 'sélectionné(s)',
                'refresh_all': 'Rafraîchir tout',
                'download_latest': 'Télécharger derniers backups',
                'create_backups': 'Créer backups',
                
                // Stats
                'total_sites': 'Total Sites',
                'total_backups': 'Total Backups',
                'last_backup': 'Dernier Backup',
                'total_size': 'Taille Totale',
                
                // Table
                'website': 'Site Web',
                'url': 'URL',
                'backups': 'Backups',
                'last_backup_col': 'Dernier Backup',
                'actions': 'Actions',
                'connected': 'Connecté',
                
                // Empty state
                'no_child_sites': 'Aucun site enfant trouvé.',
                
                // Bulk progress
                'operation_in_progress': 'Opération en cours…',
                
                // Backup list
                'loading': 'Chargement…',
                'file': 'Fichier',
                'date': 'Date',
                'size': 'Taille',
                'no_backups': 'Aucune sauvegarde sur ce site.',
                
                // Notifications
                'backup_created': 'Backup créé avec succès !',
                'backup_deleted': 'Backup supprimé.',
                'download_started': 'Téléchargement lancé !',
                'network_error': 'Erreur réseau.',
                'error': 'Erreur',
                'child_plugin_missing': 'Plugin enfant manquant.',
                'timeout': '⏱️ Timeout - Le site ne répond pas.',
                'all_lists_refreshed': 'Toutes les listes rafraîchies.',
                
                // Confirmations
                'confirm_backup': 'Lancer une nouvelle sauvegarde sur ce site ?',
                'confirm_delete': 'Supprimer cette sauvegarde ?',
                'confirm_bulk_backup': 'Créer un backup sur {count} site(s) sélectionné(s) ?\n\n⚠️ Cela peut prendre plusieurs minutes par site.',
                'confirm_bulk_download': 'Télécharger le dernier backup de {count} site(s) sélectionné(s) ?',
                
                // Progress messages
                'creating_backups': 'Création de backups en cours…',
                'downloading_backups': 'Téléchargement des derniers backups…',
                'completed': 'Terminés',
                'in_progress': 'En cours',
                'backup_in_progress': 'Backup en cours...',
                'backups_completed': 'Backups terminés : {ok}/{total} réussi(s).',
                'download_completed': 'Téléchargement lancé pour {ok} backup(s).',
                'with_errors': 'Téléchargement : {ok} réussi(s), {errors} erreur(s).',
                
                // Tooltips
                'create_backup': 'Créer un backup',
                'refresh_backups': 'Rafraîchir les backups',
                'select_all': 'Tout sélectionner',
                
                // Activity logs
                'activity_logs': 'Journaux d\'Activité',
                'no_logs': 'Aucune activité enregistrée.',
                'clear_logs': 'Effacer',
            },
            en: {
                // Header
                'ai1wm_backup_manager': 'AI1WM Backup Manager',
                'version': 'Version',
                'selected': 'selected',
                'refresh_all': 'Refresh All',
                'download_latest': 'Download Latest Backups',
                'create_backups': 'Create Backups',
                
                // Stats
                'total_sites': 'Total Sites',
                'total_backups': 'Total Backups',
                'last_backup': 'Last Backup',
                'total_size': 'Total Size',
                
                // Table
                'website': 'Website',
                'url': 'URL',
                'backups': 'Backups',
                'last_backup_col': 'Last Backup',
                'actions': 'Actions',
                'connected': 'Connected',
                
                // Empty state
                'no_child_sites': 'No child sites found.',
                
                // Bulk progress
                'operation_in_progress': 'Operation in progress…',
                
                // Backup list
                'loading': 'Loading…',
                'file': 'File',
                'date': 'Date',
                'size': 'Size',
                'no_backups': 'No backups on this site.',
                
                // Notifications
                'backup_created': 'Backup created successfully!',
                'backup_deleted': 'Backup deleted.',
                'download_started': 'Download started!',
                'network_error': 'Network error.',
                'error': 'Error',
                'child_plugin_missing': 'Child plugin missing.',
                'timeout': '⏱️ Timeout - Site not responding.',
                'all_lists_refreshed': 'All lists refreshed.',
                
                // Confirmations
                'confirm_backup': 'Create a new backup on this site?',
                'confirm_delete': 'Delete this backup?',
                'confirm_bulk_backup': 'Create backup on {count} selected site(s)?\n\n⚠️ This may take several minutes per site.',
                'confirm_bulk_download': 'Download latest backup from {count} selected site(s)?',
                
                // Progress messages
                'creating_backups': 'Creating backups…',
                'downloading_backups': 'Downloading latest backups…',
                'completed': 'Completed',
                'in_progress': 'In Progress',
                'backup_in_progress': 'Backup in progress...',
                'backups_completed': 'Backups completed: {ok}/{total} successful.',
                'download_completed': 'Download started for {ok} backup(s).',
                'with_errors': 'Download: {ok} successful, {errors} error(s).',
                
                // Tooltips
                'create_backup': 'Create a backup',
                'refresh_backups': 'Refresh backups',
                'select_all': 'Select all',
                
                // Activity logs
                'activity_logs': 'Activity Logs',
                'no_logs': 'No activity recorded.',
                'clear_logs': 'Clear',
            }
        },

        t: function(key, replacements) {
            var text = this.translations[this.currentLang][key] || key;
            if (replacements) {
                for (var k in replacements) {
                    text = text.replace('{' + k + '}', replacements[k]);
                }
            }
            return text;
        },

        setLang: function(lang) {
            this.currentLang = lang;
            localStorage.setItem('ai1wm_lang', lang);
            this.updateUI();
        },

        updateUI: function() {
            // Update all elements with data-i18n attribute
            jQuery('[data-i18n]').each(function() {
                var key = jQuery(this).data('i18n');
                var text = AI1WM_i18n.t(key);
                
                if (jQuery(this).is('input[type="checkbox"]')) {
                    jQuery(this).attr('title', text);
                } else if (jQuery(this).is('button')) {
                    // Update text but keep icons
                    var icon = jQuery(this).find('.material-icons-round').clone();
                    jQuery(this).text(text);
                    if (icon.length) {
                        jQuery(this).prepend(icon);
                    }
                } else {
                    jQuery(this).text(text);
                }
            });

            // Update language selector
            jQuery('.ai1wm-lang-btn').removeClass('active');
            jQuery('.ai1wm-lang-btn[data-lang="' + this.currentLang + '"]').addClass('active');
        }
    };

    // Initialize on page load
    jQuery(document).ready(function() {
        AI1WM_i18n.updateUI();
    });
})();
