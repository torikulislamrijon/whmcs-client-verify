/**
 * Client Verification Module — Client Area JavaScript
 *
 * Handles file upload (native Fetch API), document listing,
 * and verification status polling.
 */
(function () {
    'use strict';

    var selectedDocType = '';
    var statusPollTimer = null;

    // ─── Initialization ──────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        initDocTypeChips();
        initDropzone();
        loadMyStatus();
        loadMyDocuments();
    });

    // ─── Verification Status ─────────────────────────
    function loadMyStatus() {
        ajax('GET', 'getMyStatus', null, function (data) {
            var banner = document.getElementById('cv-status-banner');
            if (!banner) return;

            if (data.is_verified) {
                banner.className = 'cv-status-banner cv-status-verified';
                banner.innerHTML = '<i class="fas fa-check-circle"></i>'
                    + '<div class="cv-status-banner-info">'
                    + '<strong>Account Verified</strong>'
                    + '<span>Your identity has been verified. No further action is required.</span>'
                    + '</div>';
            } else {
                banner.className = 'cv-status-banner cv-status-unverified';
                banner.innerHTML = '<i class="fas fa-exclamation-triangle"></i>'
                    + '<div class="cv-status-banner-info">'
                    + '<strong>Account Not Verified</strong>'
                    + '<span>Please upload your identification documents below. Our team will review them shortly.</span>'
                    + '</div>';
            }
        });

        // Poll every 30s
        clearTimeout(statusPollTimer);
        statusPollTimer = setTimeout(loadMyStatus, 30000);
    }

    // ─── Document Type Chips ─────────────────────────
    function initDocTypeChips() {
        var container = document.getElementById('cv-doc-chips');
        if (!container || typeof cvDocTypes === 'undefined') return;

        cvDocTypes.forEach(function (type) {
            var chip = document.createElement('label');
            chip.className = 'cv-chip';
            chip.innerHTML = '<input type="radio" name="cv_doc_type" value="' + escHtml(type) + '"> ' + escHtml(type);
            container.appendChild(chip);

            chip.addEventListener('click', function () {
                document.querySelectorAll('.cv-chip').forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
                selectedDocType = type;
            });
        });
    }

    // ─── Drag & Drop Upload ──────────────────────────
    function initDropzone() {
        var dropzone = document.getElementById('cv-dropzone');
        var fileInput = document.getElementById('cv-file-input');
        if (!dropzone || !fileInput) return;

        // Drag events
        ['dragenter', 'dragover'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', function (e) {
            if (e.dataTransfer.files.length > 0) {
                handleFileUpload(e.dataTransfer.files[0]);
            }
        });

        // Click to browse
        fileInput.addEventListener('change', function () {
            if (fileInput.files.length > 0) {
                handleFileUpload(fileInput.files[0]);
                fileInput.value = '';
            }
        });
    }

    function handleFileUpload(file) {
        // Validate doc type selected
        if (!selectedDocType) {
            showNotification('Please select a document type first.', 'warning');
            return;
        }

        // Validate extension
        var ext = file.name.split('.').pop().toLowerCase();
        var allowed = (typeof cvAllowedExtensions !== 'undefined' ? cvAllowedExtensions : 'jpg,jpeg,png,gif,pdf').split(',');
        if (allowed.indexOf(ext) === -1) {
            showNotification('File type "' + ext + '" is not allowed. Allowed: ' + allowed.join(', '), 'danger');
            return;
        }

        // Validate size
        var maxSize = typeof cvMaxFileSize !== 'undefined' ? cvMaxFileSize : 10 * 1024 * 1024;
        if (file.size > maxSize) {
            showNotification('File is too large. Maximum size: ' + Math.round(maxSize / 1024 / 1024) + 'MB', 'danger');
            return;
        }

        // Show progress
        var progressWrap = document.getElementById('cv-upload-progress');
        var progressFill = document.getElementById('cv-progress-fill');
        var progressText = document.getElementById('cv-progress-text');
        var dropzoneInner = document.querySelector('.cv-dropzone-inner');

        if (dropzoneInner) dropzoneInner.style.display = 'none';
        if (progressWrap) progressWrap.style.display = 'block';
        if (progressFill) progressFill.style.width = '0%';
        if (progressText) progressText.textContent = 'Uploading ' + file.name + '...';

        // Build form data
        var formData = new FormData();
        formData.append('document', file);
        formData.append('doc_type', selectedDocType);

        // Upload via XMLHttpRequest for progress tracking
        var xhr = new XMLHttpRequest();
        xhr.open('POST', cvModuleLink + '&action=uploadDocument', true);

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                if (progressFill) progressFill.style.width = pct + '%';
                if (progressText) progressText.textContent = 'Uploading... ' + pct + '%';
            }
        });

        xhr.addEventListener('load', function () {
            // Reset UI
            if (dropzoneInner) dropzoneInner.style.display = '';
            if (progressWrap) progressWrap.style.display = 'none';

            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.status === 'ok' && resp.data && resp.data.success) {
                    showNotification('Document uploaded successfully!', 'success');
                    loadMyDocuments();
                    loadMyStatus();
                } else {
                    showNotification(resp.data && resp.data.message ? resp.data.message : 'Upload failed.', 'danger');
                }
            } catch (e) {
                showNotification('Upload failed. Invalid server response.', 'danger');
            }
        });

        xhr.addEventListener('error', function () {
            if (dropzoneInner) dropzoneInner.style.display = '';
            if (progressWrap) progressWrap.style.display = 'none';
            showNotification('Upload failed. Network error.', 'danger');
        });

        xhr.send(formData);
    }

    // ─── My Documents ────────────────────────────────
    function loadMyDocuments() {
        var loading = document.getElementById('cv-client-docs-loading');
        var table = document.getElementById('cv-client-docs-table');
        var empty = document.getElementById('cv-client-docs-empty');

        if (loading) loading.style.display = 'block';
        if (table) table.style.display = 'none';
        if (empty) empty.style.display = 'none';

        ajax('GET', 'getMyDocuments', null, function (docs) {
            if (loading) loading.style.display = 'none';

            if (!docs || docs.length === 0) {
                if (empty) empty.style.display = 'block';
                return;
            }

            var tbody = document.getElementById('cv-client-docs-body');
            if (!tbody) return;
            tbody.innerHTML = '';

            docs.forEach(function (doc) {
                var thumbHtml = doc.thumbnail
                    ? '<img class="cv-doc-preview-thumb" src="' + doc.thumbnail + '" alt="">'
                    : '<div class="cv-doc-preview-icon"><i class="fas fa-file"></i></div>';

                var statusClass = 'cv-pill-' + doc.status;

                var tr = document.createElement('tr');
                tr.innerHTML = '<td>'
                    + '<div class="cv-doc-preview">' + thumbHtml + '<span>' + escHtml(doc.file_name) + '</span></div>'
                    + '</td>'
                    + '<td>' + escHtml(doc.doc_type) + '</td>'
                    + '<td><span class="cv-pill ' + statusClass + '">' + doc.status + '</span></td>'
                    + '<td>' + (doc.created_at ? doc.created_at.substring(0, 10) : '-') + '</td>'
                    + '<td>'
                    + (doc.status === 'pending'
                        ? '<button class="cv-btn-sm cv-btn-delete cv-delete-my-doc" data-hash="' + doc.file_hash + '"><i class="fas fa-trash"></i></button>'
                        : '')
                    + '</td>';

                tbody.appendChild(tr);
            });

            // Bind delete buttons
            tbody.querySelectorAll('.cv-delete-my-doc').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!confirm('Are you sure you want to delete this document?')) return;
                    var hash = btn.getAttribute('data-hash');
                    ajax('POST', 'deleteMyDocument', { file_hash: hash }, function () {
                        showNotification('Document deleted.', 'success');
                        loadMyDocuments();
                    });
                });
            });

            // Lightbox for thumbnails
            tbody.querySelectorAll('.cv-doc-preview-thumb').forEach(function (img) {
                img.style.cursor = 'pointer';
                img.addEventListener('click', function () {
                    var overlay = document.createElement('div');
                    overlay.className = 'cv-lightbox-overlay';
                    overlay.innerHTML = '<img class="cv-lightbox-img" src="' + img.src + '">';
                    overlay.addEventListener('click', function () { overlay.remove(); });
                    document.body.appendChild(overlay);
                });
            });

            if (table) table.style.display = 'table';
        });
    }

    // ─── AJAX Helper ─────────────────────────────────
    function ajax(method, action, data, onSuccess) {
        var url = cvModuleLink + '&action=' + action;
        var options = {
            method: method,
            headers: {}
        };

        if (method === 'POST' && data) {
            var formData = new FormData();
            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    formData.append(key, data[key]);
                }
            }
            options.body = formData;
        } else if (method === 'GET' && data) {
            var params = new URLSearchParams(data);
            url += '&' + params.toString();
        }

        fetch(url, options)
            .then(function (resp) { return resp.json(); })
            .then(function (json) {
                if (json.status === 'ok' && onSuccess) {
                    onSuccess(json.data);
                } else if (json.status === 'error') {
                    showNotification(json.message || 'An error occurred.', 'danger');
                }
            })
            .catch(function (err) {
                console.error('AJAX error:', err);
            });
    }

    // ─── Helpers ─────────────────────────────────────
    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function showNotification(message, type) {
        type = type || 'info';
        var container = document.querySelector('.cv-panel') || document.body;
        var alert = document.createElement('div');
        alert.className = 'alert alert-' + type + ' alert-dismissible';
        alert.style.margin = '12px 20px';
        alert.innerHTML = '<button type="button" class="close" data-dismiss="alert">&times;</button>' + message;

        container.parentNode.insertBefore(alert, container);

        setTimeout(function () {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(function () { alert.remove(); }, 300);
        }, 5000);
    }

})();
