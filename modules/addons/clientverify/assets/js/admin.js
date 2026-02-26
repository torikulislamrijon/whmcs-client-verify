/**
 * Client Verification Module — Admin JavaScript
 *
 * Uses WHMCS-native jQuery + Bootstrap. No 3rd-party libraries.
 */
(function ($) {
    'use strict';

    var state = {
        users: {
            page: 1,
            limit: 25,
            sort: 'id',
            dir: 'asc',
            search: '',
            data: null
        },
        gateways: {
            loaded: false
        }
    };

    // ─── Initialization ──────────────────────────────
    $(document).ready(function () {
        loadUsers();
        bindEvents();

        // Load gateways when that tab is first shown
        $('a[href="#cv-tab-gateways"]').on('shown.bs.tab', function () {
            if (!state.gateways.loaded) {
                loadGateways();
                state.gateways.loaded = true;
            }
        });
    });

    function bindEvents() {
        // Search
        var searchTimer = null;
        $('#cv-user-search').on('input', function () {
            clearTimeout(searchTimer);
            var val = $(this).val();
            searchTimer = setTimeout(function () {
                state.users.search = val;
                state.users.page = 1;
                loadUsers();
            }, 300);
        });

        // Sortable headers
        $('#cv-users-table thead th[data-sort]').on('click', function () {
            var col = $(this).data('sort');
            if (state.users.sort === col) {
                state.users.dir = state.users.dir === 'asc' ? 'desc' : 'asc';
            } else {
                state.users.sort = col;
                state.users.dir = 'asc';
            }
            loadUsers();
        });
    }

    // ─── Users ───────────────────────────────────────
    function loadUsers() {
        $('#cv-users-loading').show();
        $('#cv-users-table').hide();

        $.ajax({
            url: cvModuleLink + '&action=getClients',
            type: 'GET',
            dataType: 'json',
            data: {
                page: state.users.page,
                limit: state.users.limit,
                sort: state.users.sort,
                dir: state.users.dir,
                search: state.users.search
            },
            success: function (resp) {
                if (resp.status !== 'ok') {
                    showAlert('danger', resp.message || 'Failed to load clients.');
                    return;
                }

                state.users.data = resp.data;
                renderUsers(resp.data);
            },
            error: function () {
                showAlert('danger', 'Failed to load clients. Please try again.');
            },
            complete: function () {
                $('#cv-users-loading').hide();
            }
        });
    }

    function renderUsers(data) {
        var $body = $('#cv-users-body').empty();

        if (!data.rows || data.rows.length === 0) {
            $body.html('<tr><td colspan="5" class="text-center cv-empty-state" style="padding:40px;"><i class="fas fa-users" style="font-size:2rem;opacity:0.3;"></i><p>No clients found.</p></td></tr>');
            $('#cv-users-table').show();
            $('#cv-users-pagination').empty();
            $('#cv-users-info').text('');
            return;
        }

        data.rows.forEach(function (client) {
            var verifiedChecked = client.is_verified ? 'checked' : '';
            var statusPill = client.is_verified
                ? '<span class="cv-pill cv-pill-verified"><i class="fas fa-check-circle"></i> Yes</span>'
                : '<span class="cv-pill cv-pill-unverified"><i class="fas fa-times-circle"></i> No</span>';

            var row = '<tr data-client-id="' + client.id + '">'
                + '<td><span style="font-weight:600;color:var(--cv-gray-500);">#' + client.id + '</span></td>'
                + '<td>'
                + '  <div class="cv-client-cell">'
                + '    <img class="cv-avatar" src="' + escHtml(client.gravatar) + '" alt="">'
                + '    <span class="cv-client-name">'
                + '      <a href="clientssummary.php?userid=' + client.id + '" target="_blank">'
                + escHtml(client.firstname + ' ' + client.lastname) + '</a>'
                + '    </span>'
                + '  </div>'
                + '</td>'
                + '<td>' + escHtml(client.email) + '</td>'
                + '<td class="text-center">'
                + '  <label class="cv-toggle">'
                + '    <input type="checkbox" class="cv-verify-toggle" data-client-id="' + client.id + '" ' + verifiedChecked + '>'
                + '    <span class="cv-toggle-slider"></span>'
                + '  </label>'
                + '</td>'
                + '<td class="text-center">'
                + '  <button class="cv-btn-sm cv-btn-view cv-view-docs-btn" data-client-id="' + client.id + '" data-client-name="' + escHtml(client.firstname + ' ' + client.lastname) + '">'
                + '    <i class="fas fa-file-alt"></i> Docs'
                + '  </button>'
                + '</td>'
                + '</tr>';

            $body.append(row);
        });

        // Bind toggle events
        $body.find('.cv-verify-toggle').on('change', function () {
            var $el = $(this);
            var clientId = $el.data('client-id');
            var verified = $el.is(':checked');

            $.ajax({
                url: cvModuleLink + '&action=setClientVerified',
                type: 'POST',
                dataType: 'json',
                data: { client_id: clientId, verified: verified ? 1 : 0 },
                error: function () {
                    $el.prop('checked', !verified);
                    showAlert('danger', 'Failed to update verification status.');
                }
            });
        });

        // Bind view docs
        $body.find('.cv-view-docs-btn').on('click', function () {
            var clientId = $(this).data('client-id');
            var clientName = $(this).data('client-name');
            openDocumentsModal(clientId, clientName);
        });

        // Update sort indicators
        $('#cv-users-table thead th').removeClass('sorted')
            .find('i.fas').attr('class', 'fas fa-sort');
        var $sortTh = $('#cv-users-table thead th[data-sort="' + state.users.sort + '"]');
        $sortTh.addClass('sorted')
            .find('i.fas').attr('class', 'fas fa-sort-' + (state.users.dir === 'asc' ? 'up' : 'down'));

        // Info & Pagination
        var start = (data.page - 1) * state.users.limit + 1;
        var end = Math.min(data.page * state.users.limit, data.records);
        $('#cv-users-info').text('Showing ' + start + '-' + end + ' of ' + data.records + ' clients');

        renderPagination(data.page, data.total, function (page) {
            state.users.page = page;
            loadUsers();
        });

        $('#cv-users-table').show();
    }

    // ─── Gateways ────────────────────────────────────
    function loadGateways() {
        $('#cv-gw-loading').show();
        $('#cv-gw-table').hide();

        $.ajax({
            url: cvModuleLink + '&action=getGateways',
            type: 'GET',
            dataType: 'json',
            success: function (resp) {
                if (resp.status !== 'ok') return;
                renderGateways(resp.data);
            },
            error: function () {
                showAlert('danger', 'Failed to load gateways.');
            },
            complete: function () {
                $('#cv-gw-loading').hide();
            }
        });
    }

    function renderGateways(gateways) {
        var $body = $('#cv-gw-body').empty();

        if (!gateways || gateways.length === 0) {
            $body.html('<tr><td colspan="2" class="text-center" style="padding:40px;color:var(--cv-gray-400);">No active payment gateways found.</td></tr>');
            $('#cv-gw-table').show();
            return;
        }

        gateways.forEach(function (gw) {
            var checked = gw.enforce ? 'checked' : '';
            var initial = gw.gateway.charAt(0).toUpperCase();

            var row = '<tr>'
                + '<td>'
                + '  <div class="cv-gateway-cell">'
                + '    <div class="cv-gw-icon">' + initial + '</div>'
                + '    <span class="cv-gw-name">' + escHtml(gw.gateway) + '</span>'
                + '  </div>'
                + '</td>'
                + '<td class="text-center">'
                + '  <label class="cv-toggle">'
                + '    <input type="checkbox" class="cv-gw-toggle" data-gateway="' + escHtml(gw.gateway) + '" ' + checked + '>'
                + '    <span class="cv-toggle-slider"></span>'
                + '  </label>'
                + '</td>'
                + '</tr>';

            $body.append(row);
        });

        $body.find('.cv-gw-toggle').on('change', function () {
            var $el = $(this);
            var gateway = $el.data('gateway');
            var enforce = $el.is(':checked');

            $.ajax({
                url: cvModuleLink + '&action=setGatewayEnforcement',
                type: 'POST',
                dataType: 'json',
                data: { gateway: gateway, enforce: enforce ? 1 : 0 },
                error: function () {
                    $el.prop('checked', !enforce);
                    showAlert('danger', 'Failed to update gateway setting.');
                }
            });
        });

        $('#cv-gw-table').show();
    }

    // ─── Document Review Modal ───────────────────────
    function openDocumentsModal(clientId, clientName) {
        $('#cv-docs-client-name').text(clientName);
        $('#cv-docs-list').empty();
        $('#cv-docs-empty').hide();
        $('#cv-docs-loading').show();
        $('#cv-docs-modal').modal('show');

        $.ajax({
            url: cvModuleLink + '&action=getDocuments',
            type: 'GET',
            dataType: 'json',
            data: { client_id: clientId },
            success: function (resp) {
                if (resp.status !== 'ok') return;
                renderDocuments(resp.data, clientId);
            },
            complete: function () {
                $('#cv-docs-loading').hide();
            }
        });
    }

    function renderDocuments(docs, clientId) {
        var $list = $('#cv-docs-list').empty();

        if (!docs || docs.length === 0) {
            $('#cv-docs-empty').show();
            return;
        }

        docs.forEach(function (doc) {
            var thumbHtml = doc.thumbnail
                ? '<img class="cv-doc-thumb cv-lightbox-trigger" src="' + doc.thumbnail + '" alt="' + escHtml(doc.file_name) + '">'
                : '<div class="cv-doc-thumb-placeholder"><i class="fas fa-file-pdf"></i></div>';

            var statusClass = 'cv-pill-' + doc.status;
            var card = '<div class="cv-doc-card" data-hash="' + doc.file_hash + '">'
                + thumbHtml
                + '<div class="cv-doc-info">'
                + '  <div class="cv-doc-name">' + escHtml(doc.file_name) + '</div>'
                + '  <div class="cv-doc-type"><i class="fas fa-tag"></i> ' + escHtml(doc.doc_type) + ' &middot; <span class="cv-pill ' + statusClass + '">' + doc.status + '</span></div>'
                + '  <div class="cv-doc-actions">'
                + '    <button class="cv-btn-sm cv-btn-accept cv-doc-action" data-hash="' + doc.file_hash + '" data-action="accepted"><i class="fas fa-check"></i> Accept</button>'
                + '    <button class="cv-btn-sm cv-btn-reject cv-doc-action" data-hash="' + doc.file_hash + '" data-action="rejected"><i class="fas fa-times"></i> Reject</button>'
                + '    <button class="cv-btn-sm cv-btn-delete cv-doc-delete" data-hash="' + doc.file_hash + '"><i class="fas fa-trash"></i> Delete</button>'
                + '  </div>'
                + '</div>'
                + '</div>';

            $list.append(card);
        });

        // Bind doc actions via delegation
        $list.off('click').on('click', '.cv-doc-action', function () {
            var hash = $(this).data('hash');
            var status = $(this).data('action');
            var $card = $(this).closest('.cv-doc-card');

            $.ajax({
                url: cvModuleLink + '&action=setDocumentStatus',
                type: 'POST',
                dataType: 'json',
                data: { file_hash: hash, status: status },
                success: function (resp) {
                    if (resp.status === 'ok' && resp.data && resp.data.success) {
                        // Update pill
                        $card.find('.cv-pill').attr('class', 'cv-pill cv-pill-' + status).text(status);
                    } else {
                        showAlert('danger', 'Failed to update document status.');
                    }
                }
            });
        });

        $list.on('click', '.cv-doc-delete', function () {
            var hash = $(this).data('hash');
            var $card = $(this).closest('.cv-doc-card');

            if (!confirm('Are you sure you want to delete this document?')) return;

            var $btn = $(this);
            var originalText = $btn.html();
            $btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

            $.ajax({
                url: cvModuleLink + '&action=deleteDocument',
                type: 'POST',
                dataType: 'json',
                data: { file_hash: hash },
                success: function (resp) {
                    if (resp.status === 'ok' && resp.data && resp.data.success) {
                        $card.slideUp(200, function () { $(this).remove(); });
                    } else {
                        alert('Backend Error: Could not verify deletion in database.');
                        $btn.html(originalText).prop('disabled', false);
                    }
                },
                error: function (xhr) {
                    alert('Network Error! AJAX Failed.');
                    console.error(xhr.responseText);
                    $btn.html(originalText).prop('disabled', false);
                }
            });
        });

        // Lightbox
        $list.find('.cv-lightbox-trigger').on('click', function () {
            var src = $(this).attr('src');
            var overlay = $('<div class="cv-lightbox-overlay"><img class="cv-lightbox-img" src="' + src + '"></div>');
            $('body').append(overlay);
            overlay.on('click', function () { $(this).remove(); });
        });
    }

    // ─── Pagination ──────────────────────────────────
    function renderPagination(currentPage, totalPages, callback) {
        var $pg = $('#cv-users-pagination').empty();

        if (totalPages <= 1) return;

        // Prev
        var prevDisabled = currentPage <= 1 ? ' disabled' : '';
        $pg.append('<button class="cv-page-btn' + prevDisabled + '" data-page="' + (currentPage - 1) + '"><i class="fas fa-chevron-left"></i></button>');

        // Page numbers
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            $pg.append('<button class="cv-page-btn" data-page="1">1</button>');
            if (startPage > 2) $pg.append('<span style="padding:0 6px;color:var(--cv-gray-400);">...</span>');
        }

        for (var i = startPage; i <= endPage; i++) {
            var active = i === currentPage ? ' active' : '';
            $pg.append('<button class="cv-page-btn' + active + '" data-page="' + i + '">' + i + '</button>');
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) $pg.append('<span style="padding:0 6px;color:var(--cv-gray-400);">...</span>');
            $pg.append('<button class="cv-page-btn" data-page="' + totalPages + '">' + totalPages + '</button>');
        }

        // Next
        var nextDisabled = currentPage >= totalPages ? ' disabled' : '';
        $pg.append('<button class="cv-page-btn' + nextDisabled + '" data-page="' + (currentPage + 1) + '"><i class="fas fa-chevron-right"></i></button>');

        $pg.find('.cv-page-btn').not(':disabled').on('click', function () {
            callback(parseInt($(this).data('page'), 10));
        });
    }

    // ─── Helpers ─────────────────────────────────────
    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function showAlert(type, message) {
        var $alert = $('<div class="alert alert-' + type + ' alert-dismissible" style="margin:12px 0;">'
            + '<button type="button" class="close" data-dismiss="alert">&times;</button>'
            + message + '</div>');
        $('.cv-admin-wrap').prepend($alert);
        setTimeout(function () { $alert.fadeOut(300, function () { $(this).remove(); }); }, 5000);
    }

})(jQuery);
