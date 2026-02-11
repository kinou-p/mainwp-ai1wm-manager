(function ($) {
    'use strict';

    var nonce = ai1wm_vars.nonce;
    var backupsCache = {}; // site_id -> backups array

    /* ==== Retry Logic with Exponential Backoff ==== */
    function ajaxWithRetry(options, maxRetries) {
        maxRetries = maxRetries || 2;
        var attempt = 0;

        function tryRequest() {
            attempt++;
            var deferred = $.Deferred();

            $.ajax(options)
                .done(function(data, textStatus, xhr) {
                    deferred.resolve(data, textStatus, xhr);
                })
                .fail(function(xhr, status, error) {
                    if (attempt < maxRetries && status !== 'timeout') {
                        // Exponential backoff: 1s, 2s, 4s...
                        var delay = Math.pow(2, attempt) * 1000;
                        setTimeout(function() {
                            tryRequest();
                        }, delay);
                    } else {
                        deferred.reject(xhr, status, error);
                    }
                });

            return deferred.promise();
        }

        return tryRequest();
    }

    /* ==== Notifications ==== */
    function notify(msg, type) {
        type = type || 'info';
        var $el = $('<div class="ai1wm-notice ai1wm-notice-' + type + '">' + msg + '</div>');
        $('body').append($el);
        setTimeout(function () { $el.fadeOut(300, function () { $el.remove(); }); }, 4000);
    }

    /* ==== Button Loading ==== */
    function btnLoading($btn, on) {
        if (on) {
            $btn.data('orig-html', $btn.html()).prop('disabled', true)
                .html('<span class="ai1wm-spinner"></span> ...');
        } else {
            $btn.prop('disabled', false).html($btn.data('orig-html'));
        }
    }

    /* ==== Format Size ==== */
    function fmtSize(b) {
        if (!b) return '0 B';
        var u = ['B', 'KB', 'MB', 'GB'], i = Math.floor(Math.log(b) / Math.log(1024));
        return (b / Math.pow(1024, i)).toFixed(1) + ' ' + u[i];
    }

    /* ==== Escape HTML ==== */
    function esc(s) { return $('<span>').text(s).html(); }

    /* ==== Render Backups Dropdown ==== */
    function renderBackups(siteId, backups) {
        backupsCache[siteId] = backups;
        var $row = $('.ai1wm-backups-row[data-site-id="' + siteId + '"]');
        var $content = $row.find('.ai1wm-backups-content');
        var $count = $('.ai1wm-backup-count[data-site-id="' + siteId + '"]');

        $count.text(backups ? backups.length : 0);

        if (!backups || backups.length === 0) {
            $content.html('<p style="color:var(--ai-text-dim);font-style:italic;margin:0;">Aucune sauvegarde sur ce site.</p>');
            return;
        }

        var h = '<table class="ai1wm-backups-list"><thead><tr>' +
            '<th>Fichier</th><th>Date</th><th>Taille</th><th style="width:140px;text-align:right;">Actions</th>' +
            '</tr></thead><tbody>';

        $.each(backups, function (i, b) {
            h += '<tr>' +
                '<td><span class="material-icons-round file-icon">description</span>' + esc(b.name) + '</td>' +
                '<td style="color:var(--ai-text-muted);">' + esc(b.date) + '</td>' +
                '<td style="color:var(--ai-text-muted);">' + fmtSize(b.size) + '</td>' +
                '<td style="text-align:right;">' +
                '<button class="ai1wm-btn ai1wm-btn-dl ai1wm-btn-sm" data-site-id="' + siteId + '" data-file="' + esc(b.name) + '"><span class="material-icons-round" style="font-size:16px;">download</span> DL</button> ' +
                '<button class="ai1wm-btn ai1wm-btn-delete ai1wm-btn-sm" data-site-id="' + siteId + '" data-file="' + esc(b.name) + '"><span class="material-icons-round" style="font-size:16px;">delete_outline</span></button>' +
                '</td></tr>';
        });
        h += '</tbody></table>';
        $content.html(h);

        // Update stats
        updateStats();
    }

    /* ==== Checkbox Selection ==== */
    function getSelected() {
        var ids = [];
        $('.ai1wm-site-cb:checked').each(function () { ids.push($(this).val()); });
        return ids;
    }
    function updateSelectionUI() {
        var n = getSelected().length;
        $('#ai1wm-selected-count .count-num').text(n);
        $('#ai1wm-bulk-backup, #ai1wm-bulk-download').prop('disabled', n === 0);
    }

    /* ==== Update Stats Cards ==== */
    function updateStats() {
        var totalBackups = 0;
        var latestDate = '';
        for (var sid in backupsCache) {
            var arr = backupsCache[sid];
            if (arr && arr.length) {
                totalBackups += arr.length;
                if (arr[0].date && arr[0].date > latestDate) latestDate = arr[0].date;
            }
        }
        $('#ai1wm-stat-backups').text(totalBackups || '–');
        $('#ai1wm-stat-last').text(latestDate || '–');
    }
    $(document).on('change', '.ai1wm-site-cb', updateSelectionUI);
    $(document).on('change', '#ai1wm-select-all', function () {
        $('.ai1wm-site-cb').prop('checked', $(this).is(':checked'));
        updateSelectionUI();
    });

    /* ==== Toggle Dropdown ==== */
    $(document).on('click', '.ai1wm-toggle', function () {
        var $t = $(this);
        var siteId = $t.data('site-id');
        var $row = $('.ai1wm-backups-row[data-site-id="' + siteId + '"]');

        if ($t.hasClass('open')) {
            $t.removeClass('open');
            $row.slideUp(150);
        } else {
            $t.addClass('open');
            $row.slideDown(150);
            // Auto-load if not cached
            if (!backupsCache[siteId]) {
                loadBackups(siteId);
            }
        }
    });

    /* ==== AJAX: List Backups ==== */
    function loadBackups(siteId, callback) {
        var $content = $('.ai1wm-backups-row[data-site-id="' + siteId + '"] .ai1wm-backups-content');
        $content.html('<span class="ai1wm-spinner ai1wm-spinner-dark"></span> Chargement…');

        ajaxWithRetry({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'ai1wm_list_backups',
                site_id: siteId,
                _nonce: nonce
            },
            timeout: 30000 // 30 seconds timeout
        }, 2).done(function (res) {
            if (res.success) {
                renderBackups(siteId, res.data);
            } else {
                if (res.data === 'PLUGIN_MISSING') {
                    // Close accordion
                    var $toggle = $('.ai1wm-toggle[data-site-id="' + siteId + '"]');
                    $toggle.removeClass('open');
                    $('.ai1wm-backups-row[data-site-id="' + siteId + '"]').slideUp(150);

                    $content.html('<p style="color:var(--ai-danger);">Plugin enfant non détecté sur le site.</p>');
                    notify('Plugin enfant manquant.', 'error');
                } else {
                    $content.html('<p style="color:var(--ai-danger);">Erreur : ' + esc(res.data || 'Inconnue') + '</p>');
                }
            }
            if (callback) callback(res.success);
        }).fail(function (xhr, status, error) {
            if (status === 'timeout') {
                $content.html('<p style="color:var(--ai-danger);">⏱️ Timeout - Le site ne répond pas.</p>');
            } else {
                $content.html('<p style="color:var(--ai-danger);">❌ Erreur réseau: ' + error + '</p>');
            }
            if (callback) callback(false);
        });
    }

    $(document).on('click', '.ai1wm-btn-list', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var siteId = $btn.data('site-id');
        btnLoading($btn, true);
        loadBackups(siteId, function () {
            btnLoading($btn, false);
            // Open the dropdown
            var $toggle = $('.ai1wm-toggle[data-site-id="' + siteId + '"]');
            if (!$toggle.hasClass('open')) $toggle.trigger('click');
        });
    });

    /* ==== AJAX: Create Backup ==== */
    $(document).on('click', '.ai1wm-btn-create', function (e) {
        e.preventDefault();
        var $btn = $(this), siteId = $btn.data('site-id');
        if (!confirm('Lancer une nouvelle sauvegarde sur ce site ?')) return;
        btnLoading($btn, true);

        // Add visual indicator in the row
        var $row = $('.ai1wm-site-row[data-site-id="' + siteId + '"]');
        var $statusBadge = $('<span class="ai1wm-backup-status" style="margin-left:10px;padding:4px 8px;background:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.3);border-radius:4px;font-size:11px;color:#3b82f6;"><span class="ai1wm-spinner ai1wm-spinner-sm" style="display:inline-block;width:10px;height:10px;margin-right:5px;"></span>Backup en cours...</span>');
        $row.find('.site-name').append($statusBadge);

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'ai1wm_create_backup',
                site_id: siteId,
                _nonce: nonce
            },
            timeout: 120000, // 2 minutes timeout
            success: function (res) {
                btnLoading($btn, false);
                $statusBadge.remove();
                
                if (res.success) {
                    notify('✅ Sauvegarde lancée ! La création peut prendre quelques minutes...', 'success');
                    delete backupsCache[siteId];
                    loadLogs(); // Refresh logs immediately
                    
                    // Show "processing" badge
                    var $processingBadge = $('<span class="ai1wm-backup-status" style="margin-left:10px;padding:4px 8px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);border-radius:4px;font-size:11px;color:#22c55e;">⚙️ Traitement...</span>');
                    $row.find('.site-name').append($processingBadge);
                    
                    // Auto-refresh after 30 seconds to check if backup is complete
                    setTimeout(function() {
                        loadBackups(siteId);
                        loadLogs(); // Refresh logs again
                        $processingBadge.remove();
                        notify('🔄 Vérification du statut de la sauvegarde...', 'info');
                    }, 30000);
                } else {
                    notify('❌ ' + (res.data || 'Erreur'), 'error');
                    loadLogs(); // Refresh logs even on error
                }
            },
            error: function (xhr, status, error) {
                btnLoading($btn, false);
                $statusBadge.remove();
                
                if (status === 'timeout') {
                    notify('⏱️ Timeout - La sauvegarde peut toujours être en cours. Vérifiez dans quelques minutes.', 'info');
                    // Still try to refresh after timeout
                    setTimeout(function() { loadBackups(siteId); }, 60000);
                } else {
                    notify('❌ Erreur réseau: ' + error, 'error');
                }
            }
        });
    });

    /* ==== AJAX: Delete Backup ==== */
    $(document).on('click', '.ai1wm-btn-delete', function (e) {
        e.preventDefault();
        var $btn = $(this), siteId = $btn.data('site-id'), file = $btn.data('file');
        if (!confirm('Supprimer « ' + file + ' » ?')) return;
        btnLoading($btn, true);

        $.post(ajaxurl, {
            action: 'ai1wm_delete_backup',
            site_id: siteId,
            file_name: file,
            _nonce: nonce
        }, function (res) {
            btnLoading($btn, false);
            if (res.success) {
                notify('✅ Fichier supprimé !', 'success');
                delete backupsCache[siteId];
                loadBackups(siteId);
                loadLogs(); // Refresh logs after deletion
            } else {
                notify('❌ ' + (res.data || 'Erreur'), 'error');
                loadLogs(); // Refresh logs even on error
            }
        }).fail(function () {
            btnLoading($btn, false);
            notify('❌ Erreur réseau.', 'error');
            loadLogs(); // Refresh logs on network error
        });
    });

    /* ==== AJAX: Download Backup ==== */
    $(document).on('click', '.ai1wm-btn-dl', function (e) {
        e.preventDefault();
        var $btn = $(this), siteId = $btn.data('site-id'), file = $btn.data('file');
        btnLoading($btn, true);

        $.post(ajaxurl, {
            action: 'ai1wm_download_backup',
            site_id: siteId,
            file_name: file,
            _nonce: nonce
        }, function (res) {
            btnLoading($btn, false);
            if (res.success && res.data && res.data.url) {
                // Open download in new tab
                var a = document.createElement('a');
                a.href = res.data.url;
                a.download = file;
                a.target = '_blank';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                notify('✅ Téléchargement lancé !', 'success');
            } else {
                notify('❌ ' + (res.data || 'Téléchargement impossible'), 'error');
            }
        }).fail(function () {
            btnLoading($btn, false);
            notify('❌ Erreur réseau.', 'error');
        });
    });

    /* ==== Bulk: Backup Selected (with concurrency limit) ==== */
    $('#ai1wm-bulk-backup').on('click', function () {
        var ids = getSelected();
        if (!ids.length) return;
        if (!confirm('Lancer une sauvegarde sur ' + ids.length + ' site(s) ?')) return;

        var $prog = $('#ai1wm-bulk-progress').show();
        $('#ai1wm-bulk-title').text('Création de backups…');
        var done = 0, total = ids.length, ok = 0, errors = [];
        var concurrency = 3; // Process 3 sites at a time
        var queue = ids.slice();
        var running = 0;

        function updateProgress() {
            done++;
            var pct = Math.round((done / total) * 100);
            $('#ai1wm-progress-fill').css('width', pct + '%');
            $('#ai1wm-progress-text').text(done + ' / ' + total + (ok > 0 ? ' (✅ ' + ok + ')' : ''));
            
            if (done >= total) {
                setTimeout(function () { $prog.slideUp(200); }, 3000);
                var msg = '✅ Backup terminé : ' + ok + '/' + total + ' réussi(s).';
                if (errors.length > 0) {
                    msg += ' Erreurs: ' + errors.join(', ');
                }
                notify(msg, ok === total ? 'success' : 'error');
                loadLogs(); // Refresh logs after bulk operation completes
            }
        }

        function processNext() {
            if (queue.length === 0) {
                running--;
                return;
            }

            var siteId = queue.shift();
            running++;

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'ai1wm_create_backup',
                    site_id: siteId,
                    _nonce: nonce
                },
                timeout: 120000
            }).done(function (res) {
                if (res.success) ok++;
                else errors.push('Site ' + siteId);
                delete backupsCache[siteId];
            }).fail(function () {
                errors.push('Site ' + siteId + ' (network)');
            }).always(function () {
                updateProgress();
                running--;
                processNext();
            });
        }

        // Start initial batch
        for (var i = 0; i < Math.min(concurrency, ids.length); i++) {
            processNext();
        }
    });

    /* ==== Bulk: Download Latest from Selected ==== */
    $('#ai1wm-bulk-download').on('click', function () {
        var ids = getSelected();
        if (!ids.length) return;

        var $prog = $('#ai1wm-bulk-progress').show();
        $('#ai1wm-bulk-title').text('Téléchargement des dernières backups…');
        var done = 0, total = ids.length, ok = 0;

        function updateProgress() {
            done++;
            var pct = Math.round((done / total) * 100);
            $('#ai1wm-progress-fill').css('width', pct + '%');
            $('#ai1wm-progress-text').text(done + ' / ' + total);
            if (done >= total) {
                setTimeout(function () { $prog.slideUp(200); }, 2000);
                notify('✅ Téléchargement : ' + ok + '/' + total + ' lancé(s).', ok === total ? 'success' : 'info');
                loadLogs(); // Refresh logs after bulk download completes
            }
        }

        function doDownload(siteId) {
            // First get the list to find the latest backup
            $.post(ajaxurl, {
                action: 'ai1wm_list_backups',
                site_id: siteId,
                _nonce: nonce
            }, function (res) {
                if (res.success && res.data && res.data.length > 0) {
                    var latest = res.data[0]; // Already sorted desc
                    renderBackups(siteId, res.data);
                    // Now download it
                    $.post(ajaxurl, {
                        action: 'ai1wm_download_backup',
                        site_id: siteId,
                        file_name: latest.name,
                        _nonce: nonce
                    }, function (dlRes) {
                        if (dlRes.success && dlRes.data && dlRes.data.url) {
                            var a = document.createElement('a');
                            a.href = dlRes.data.url;
                            a.download = latest.name;
                            a.target = '_blank';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            ok++;
                        }
                        updateProgress();
                    }).fail(function () { updateProgress(); });
                } else {
                    updateProgress();
                }
            }).fail(function () { updateProgress(); });
        }

        $.each(ids, function (i, siteId) {
            // Stagger downloads by 500ms to avoid overwhelming the browser
            setTimeout(function () { doDownload(siteId); }, i * 500);
        });
    });

    /* ==== Refresh All ==== */
    $('#ai1wm-refresh-all').on('click', function () {
        var $btn = $(this);
        btnLoading($btn, true);
        backupsCache = {};
        var total = $('.ai1wm-site-row').length, done = 0;

        $('.ai1wm-site-row').each(function () {
            var siteId = $(this).data('site-id');
            loadBackups(siteId, function () {
                done++;
                if (done >= total) {
                    btnLoading($btn, false);
                    notify('✅ Toutes les listes rafraîchies.', 'success');
                }
            });
        });
    });

    /* ==== Logs System ==== */
    function loadLogs() {
        var $container = $('#ai1wm-logs-body');

        $.post(ajaxurl, {
            action: 'ai1wm_get_logs',
            _nonce: nonce
        }, function (res) {
            if (res.success) {
                var logs = res.data;
                if (!logs || logs.length === 0) {
                    $container.html('<tr><td style="padding:20px;text-align:center;color:var(--ai-text-dim);">Aucun log disponible.</td></tr>');
                    return;
                }

                var html = '';
                $.each(logs, function (i, log) {
                    var color = '#94a3b8'; // text-muted
                    var icon = 'info';
                    if (log.type === 'success') { color = 'var(--ai-primary)'; icon = 'check_circle'; }
                    if (log.type === 'error') { color = 'var(--ai-danger)'; icon = 'error'; }

                    var siteBadge = '';
                    if (log.site_name) {
                        siteBadge = '<span style="background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:4px;font-size:10px;margin-right:8px;color:#cbd5e1;">' + esc(log.site_name) + '</span>';
                    }

                    html += '<tr style="border-bottom:1px solid rgba(255,255,255,0.05);">' +
                        '<td style="padding:10px 16px;width:140px;color:var(--ai-text-dim);font-family:monospace;font-size:11px;">' + esc(log.timestamp) + '</td>' +
                        '<td style="padding:10px 16px;">' +
                        '<div style="display:flex;align-items:center;">' +
                        '<span class="material-icons-round" style="font-size:16px;color:' + color + ';margin-right:8px;">' + icon + '</span>' +
                        siteBadge +
                        '<span style="color:#e2e8f0;">' + esc(log.message) + '</span>' +
                        '</div>' +
                        '</td>' +
                        '</tr>';
                });
                $container.html(html);
            } else {
                $container.html('<tr><td style="padding:20px;text-align:center;color:var(--ai-danger);">Erreur chargement logs.</td></tr>');
            }
        });
    }

    // Load logs on init
    loadLogs();

    // Auto-refresh logs every 10 seconds
    var logsAutoRefreshInterval = setInterval(function() {
        loadLogs();
    }, 10000);

    // Stop auto-refresh if user leaves the page
    $(window).on('beforeunload', function() {
        clearInterval(logsAutoRefreshInterval);
    });

    // Refresh logs button
    $('#ai1wm-refresh-logs').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.find('.material-icons-round').addClass('fa-spin'); // Optional spin if using FA, but here just visual feedback
        $btn.prop('disabled', true);
        loadLogs();
        setTimeout(function () { 
            $btn.prop('disabled', false); 
            $btn.find('.material-icons-round').removeClass('fa-spin');
        }, 500);
    });

    // Clear logs button
    $('#ai1wm-clear-logs').on('click', function (e) {
        e.preventDefault();
        if (!confirm('Voulez-vous vraiment effacer l\'historique ?')) return;

        $.post(ajaxurl, {
            action: 'ai1wm_clear_logs',
            _nonce: nonce
        }, function (res) {
            if (res.success) {
                loadLogs();
                notify('Logs effacés.', 'info');
            }
        });
    });

    /* ==== Sorting System ==== */
    var currentSort = { col: null, dir: 'asc' };

    function sortSites(col) {
        var dir = 'asc';
        if (currentSort.col === col && currentSort.dir === 'asc') {
            dir = 'desc';
        }
        currentSort = { col: col, dir: dir };

        // Update headers
        $('.ai1wm-sortable').removeClass('sorted-asc sorted-desc').find('.sort-icon').text('unfold_more');
        var $th = $('.ai1wm-sortable[data-sort="' + col + '"]');
        $th.addClass(dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
        $th.find('.sort-icon').text(dir === 'asc' ? 'expand_less' : 'expand_more');

        var $tbody = $('#ai1wm-sites-table tbody');
        // Get all site rows (exclude logs or other rows if any, but currently only sites + backup rows)
        var rows = $tbody.find('.ai1wm-site-row').get();

        rows.sort(function (a, b) {
            var valA, valB;
            var $a = $(a), $b = $(b);

            if (col === 'name') {
                valA = $a.find('.site-name').text().trim().toLowerCase();
                valB = $b.find('.site-name').text().trim().toLowerCase();
            } else if (col === 'url') {
                valA = $a.find('.site-url').text().trim().replace(/^https?:\/\/(www\.)?/, '').toLowerCase();
                valB = $b.find('.site-url').text().trim().replace(/^https?:\/\/(www\.)?/, '').toLowerCase();
            } else if (col === 'backups') {
                var txtA = $a.find('.ai1wm-backup-count').text().trim();
                var txtB = $b.find('.ai1wm-backup-count').text().trim();
                // Treat "-" as -1 for sorting
                valA = txtA === '–' || txtA === '-' ? -1 : parseInt(txtA, 10);
                valB = txtB === '–' || txtB === '-' ? -1 : parseInt(txtB, 10);
            }

            if (valA < valB) return dir === 'asc' ? -1 : 1;
            if (valA > valB) return dir === 'asc' ? 1 : -1;
            return 0;
        });

        // Reorder rows (and their associated backup rows)
        $.each(rows, function (i, row) {
            var $row = $(row);
            var $next = $row.next('.ai1wm-backups-row'); // The hidden details row
            $tbody.append($row);
            $tbody.append($next); // Move it right after
        });
    }

    $('.ai1wm-sortable').on('click', function () {
        var col = $(this).data('sort');
        sortSites(col);
    });

})(jQuery);
